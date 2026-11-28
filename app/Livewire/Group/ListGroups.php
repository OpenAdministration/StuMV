<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanListGroup;
use App\Models\GroupMembership;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
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

    public string $deleteGroupDn = '';

    public string $deleteGroupName = '';

    public string $deleteConfirmText = '';

    public function sortBy(string $field): void
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

    public function mount(Community $realm): void
    {
        $this->realm_uid = $realm->getShortCode();
    }

    public function render(): View
    {
        $groupsQuery = Group::query()->in(Group::dnRoot($this->realm_uid));
        if ($this->search) {
            $search = trim($this->search);
            $groupsQuery->whereContains('cn', $search)
                ->orWhereContains('description', $search);
        }
        $sorted = $groupsQuery->get()
            ->sortBy(fn ($group): string => mb_strtolower((string) $group->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
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

    public function deletePrepare(string $uid, string $cn): void
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
            $this->addError('deleteConfirmText', __('common.does_not_equal', ['text' => $this->deleteGroupName]));

            return;
        }

        $group = Group::findOrFail($this->deleteGroupDn);
        $roleDns = GroupMembership::where('group_dn', $this->deleteGroupDn)->pluck('role_dn')->all();

        // Delete role group relationships
        GroupMembership::where('group_dn', $this->deleteGroupDn)->delete();

        // each->delete() so the list's sync status cache is reset
        GroupMailmanListGroup::where('group_dn', $this->deleteGroupDn)->get()->each->delete();

        // Delete group
        Group::query()->delete($this->deleteGroupDn);
        resolve(ActivityLogger::class)->recordEntry('group.deleted', $this->realm_uid, $group, [
            'dn' => $group->getDn(),
            'description' => $group->getFirstAttribute('description'),
            'roles' => $roleDns,
        ]);

        // reset everything to prevent a 404 modal
        $this->reset('deleteGroupDn');
        Flux::modal('delete')->close();
    }
}
