<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMembership;
use Flux\Flux;
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

    public function mount(Community $uid)
    {
        $this->realm_uid = $uid->getShortCode();
    }

    public function render()
    {
        $groupsQuery = Group::query()->in(Group::dnRoot($this->realm_uid));
        if ($this->search) {
            $groupsQuery->whereContains('cn', trim($this->search));
        }
        $groups = $groupsQuery->get();

        return view('livewire.group.list-group', [
            'groups' => $groups,
        ])->title(__('groups.list_title'));
    }

    public function deletePrepare($uid, $cn): void
    {
        $this->deleteGroupDn = Group::dnFrom($uid, $cn);
        $this->deleteGroupName = $cn;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        // Delete role group relationships
        GroupMembership::where('group_dn', $this->deleteGroupDn)->delete();

        // Delete group
        Group::query()->delete($this->deleteGroupDn);

        // reset everything to prevent a 404 modal
        unset($this->deleteGroupDn);
        Flux::modal('delete')->close();
    }
}
