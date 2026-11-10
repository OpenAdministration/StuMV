<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\GroupMailmanListGroup;
use App\Support\MailmanListSyncStatus;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

    /** @var array<int, array{state: string, text: string}> keyed by mapping id */
    public array $statuses = [];

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

    /**
     * Loads the Mailman status of every row on the current page that doesn't have one yet, in one batch.
     */
    public function loadStatuses(MailmanListSyncStatus $status): void
    {
        $missing = $this->currentPage()->getCollection()->reject(fn (GroupMailmanList $m): bool => isset($this->statuses[$m->id]));

        $this->statuses = $status->forMany($missing) + $this->statuses;
    }

    public function render()
    {
        $webUrl = rtrim((string) config('services.mailman.web_url'), '/');
        $existingGroupDns = Group::query()->in(Group::dnRoot($this->uid))->get()
            ->map(fn (Group $group): string => strtolower((string) $group->getDn()))
            ->flip();

        $mappings = $this->currentPage()->through(function (GroupMailmanList $mapping) use ($webUrl, $existingGroupDns): object {
            $groups = collect(GroupMailmanListGroup::ROLES)->mapWithKeys(fn (string $role): array => [$role => array_map(fn (string $dn): array => [
                'cn' => Group::shortNameFromDn($dn),
                'exists' => $existingGroupDns->has(strtolower($dn)),
            ], $mapping->groupDns($role))])->all();

            return (object) [
                'web_url' => $webUrl !== '' ? "{$webUrl}/postorius/lists/".rawurlencode($mapping->mailman_list_id).'/' : null,
                'id' => $mapping->id,
                'groups' => $groups,
                // Same guard as mailman:sync-lists: no member group or a deleted group means nothing gets synced.
                'groups_complete' => $groups['member'] !== [] && collect($groups)->flatten(1)->every(fn (array $group): bool => $group['exists']),
                'mailman_list_id' => $mapping->mailman_list_id,
                'enabled' => $mapping->enabled,
            ];
        });

        $missingStatusIds = collect($mappings->items())->pluck('id')->reject(fn (int $id): bool => isset($this->statuses[$id]))->implode(',');

        return view('livewire.mailman.list-group-mailman-lists', ['mappings' => $mappings, 'missingStatusIds' => $missingStatusIds])
            ->title(__('group_mailman_lists.list_title'));
    }

    private function currentPage(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        // group_dn starts with "cn=<short name>,", so it sorts and searches by the group's short name.
        return GroupMailmanList::where('realm', $this->uid)
            ->with('groups')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('mailman_list_id', 'like', "%{$search}%")
                ->orWhereHas('groups', fn ($groups) => $groups->where('group_dn', 'like', "cn=%{$search}%,ou=Groups,%"))))
            ->when(
                $this->sortField === 'group',
                fn ($query) => $query->orderBy(GroupMailmanListGroup::select('group_dn')
                    ->whereColumn('group_mailman_list_id', 'group_mailman_lists.id')
                    ->where('role', 'member')
                    ->orderBy('group_dn')
                    ->limit(1), $direction),
                fn ($query) => $query->orderBy('mailman_list_id', $direction),
            )
            ->paginate(10);
    }

    public function toggleEnabled(int $mappingId): void
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($mappingId);
        $mapping->update(['enabled' => ! $mapping->enabled]);
        unset($this->statuses[$mapping->id]);

        Flux::toast(variant: 'success', text: __($mapping->enabled ? 'group_mailman_lists.enabled_success' : 'group_mailman_lists.disabled_success', ['name' => $mapping->mailman_list_id]));
    }

    public function syncList(int $mappingId): void
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($mappingId);

        if (! $mapping->enabled) {
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.list_disabled', ['name' => $mapping->mailman_list_id]));

            return;
        }

        // Large lists can take minutes; the web default of 30s would kill the sync midway.
        set_time_limit(600);

        try {
            $exitCode = Artisan::call('mailman:sync-lists', ['realm' => $this->uid, '--list' => $mapping->mailman_list_id]);
        } catch (Throwable $e) {
            report($e);
            $exitCode = 1;
        }
        unset($this->statuses[$mapping->id]);

        if ($exitCode === 0) {
            Flux::toast(variant: 'success', text: __('group_mailman_lists.sync_success', ['name' => $mapping->mailman_list_id]));
        } else {
            // Artisan::call() swallows the command's own output otherwise.
            Log::error('mailman:sync-lists failed', ['realm' => $this->uid, 'list_id' => $mapping->mailman_list_id, 'output' => Artisan::output()]);
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
