<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ListGroupMailmanLists extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'mailman_list_id';

    #[Url]
    public string $sortDirection = 'asc';

    public string $uid;

    public ?int $deleteMappingId = null;

    public string $deleteMappingLabel = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->search);
        // group_dn starts with "cn=<short name>,", so it sorts and searches by the group's short name.
        $sortColumn = $this->sortField === 'group' ? 'group_dn' : 'mailman_list_id';

        $mappings = GroupMailmanList::where('realm', $this->uid)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('mailman_list_id', 'like', "%{$search}%")
                ->orWhere('group_dn', 'like', "cn=%{$search}%,ou=Groups,%")))
            ->orderBy($sortColumn, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(10)
            ->through(fn (GroupMailmanList $mapping): object => (object) [
                'id' => $mapping->id,
                'group_cn' => substr(explode(',', $mapping->group_dn, 2)[0], 3),
                'group_exists' => Group::find($mapping->group_dn) !== null,
                'mailman_list_id' => $mapping->mailman_list_id,
            ]);

        return view('livewire.mailman.list-group-mailman-lists', ['mappings' => $mappings])
            ->title(__('group_mailman_lists.list_title'));
    }

    public function syncList(int $mappingId): void
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($mappingId);
        // Large lists can take minutes; the web default of 30s would kill the sync midway.
        set_time_limit(600);

        try {
            $exitCode = Artisan::call('mailman:sync-lists', ['realm' => $this->uid, '--list' => $mapping->mailman_list_id]);
        } catch (Throwable $e) {
            report($e);
            $exitCode = 1;
        }

        if ($exitCode === 0) {
            Flux::toast(variant: 'success', text: __('group_mailman_lists.sync_success', ['name' => $mapping->mailman_list_id]));
        } else {
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.sync_failed', ['name' => $mapping->mailman_list_id]));
        }
    }

    public function deletePrepare(int $mappingId): void
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($mappingId);
        $this->deleteMappingId = $mapping->id;
        $this->deleteMappingLabel = $mapping->mailman_list_id;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        GroupMailmanList::where('realm', $this->uid)->findOrFail($this->deleteMappingId)->delete();

        Flux::toast(variant: 'success', text: __('group_mailman_lists.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        $this->reset('deleteMappingId', 'deleteMappingLabel');
    }
}
