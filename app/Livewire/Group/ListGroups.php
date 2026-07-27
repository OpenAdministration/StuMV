<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMembership;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListGroups extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    public string $realm_uid;

    public string $deleteGroupDn;

    public string $deleteGroupName = '';

    public string $deleteConfirmText = '';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            // toggle direction
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

    public function mount(Community $realm)
    {
        $this->realm_uid = $realm->getShortCode();
    }

    public function render()
    {
        $groupsQuery = Group::query()->in(Group::dnRoot($this->realm_uid));
        if ($this->search) {
            $search = trim($this->search);
            $groupsQuery->whereContains('cn', $search)
                ->orWhereContains('description', $search);
        }
        $sorted = $groupsQuery->get()
            ->sortBy(fn ($group) => mb_strtolower((string) $group->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        // LdapRecord collections aren't Eloquent builders, so pagination is
        // done manually: sort the full result, then slice out the page.
        $perPage = 10;
        $page = $this->getPage();
        $groups = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view('livewire.group.list-group', [
            'groups' => $groups,
        ])->title(__('groups.list_title'));
    }

    public function deletePrepare($uid, $cn): void
    {
        // $uid is only ever the realm this page is already scoped to (see
        // list-group.blade.php) - always deriving the DN from realm_uid
        // instead of the passed-in argument stops a client from pointing
        // this at an arbitrary other realm's group via a crafted Livewire
        // call.
        $this->deleteGroupDn = Group::dnFrom($this->realm_uid, $cn);
        $this->deleteGroupName = $cn;
        $this->deleteConfirmText = '';
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        // $deleteGroupDn has no #[Locked], so it must be re-checked here too
        // (not just in deletePrepare()) before deleting - otherwise a client
        // could set it directly to a DN in a different realm.
        abort_unless(str_ends_with($this->deleteGroupDn, ','.Group::dnRoot($this->realm_uid)), 404);

        if ($this->deleteConfirmText !== $this->deleteGroupName) {
            $this->addError('deleteConfirmText', __('Does not equal :text', ['text' => $this->deleteGroupName]));

            return;
        }

        // Delete role group relationships
        GroupMembership::where('group_dn', $this->deleteGroupDn)->delete();

        // Delete group
        Group::query()->delete($this->deleteGroupDn);

        // reset everything to prevent a 404 modal
        unset($this->deleteGroupDn);
        Flux::modal('delete')->close();
    }
}
