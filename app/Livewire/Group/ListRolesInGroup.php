<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRolesInGroup extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'name';
    #[Url]
    public string $sortDirection = 'asc';

    public string $group_dn;
    public string $group_cn;
    public string $realm_uid;


    public bool $showDeleteModal = false;
    public string $deleteRoleDN = "";
    public array $deleteRoleName = [];

    public function mount(Community $uid, $cn) {
        $this->realm_uid = $uid->getFirstAttribute('ou');
        $this->group_cn = $cn;
        $this->group_dn = Group::dnFrom($this->realm_uid, $cn);
    }

    public function sortBy($field){
        if($this->sortField === $field){
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }else{
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render() {
        /** @var Group $group */
        $group = Group::findOrFail($this->group_dn);
        $roles = $group->members()->get();
        $users = $group->users()->get();
        // slice breaks it, whyever - get to go.
        return view(
            'livewire.group.roles', [
                'roles' => $roles,
                'users' => $users,
                'group' => $group,
            ]
        )->title(__('groups.roles_list_title', ['name' => $this->group_cn]));
    }

    public function deletePrepare(string $role_dn): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        $group = Group::findOrFail($this->group_dn);
        $role = Role::findOrFail($role_dn);
        $committee = $role->committee();

        $this->deleteRoleDN = $role_dn;
        $this->deleteRoleName = [
            'role_short' => $role->getFirstAttribute('description'),
            'role_name' => $role->getFirstAttribute('cn'),
            'committee_name' => $committee?->getFirstAttribute('description'),
            'committee_short' => $committee?->getFirstAttribute('ou'),
            'group_short' => $group->getFirstAttribute('cn'),
            'group_name' => $group->getFirstAttribute('description')
        ];

        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        $group = Group::findOrFail($this->group_dn);
        $role = Role::findOrFail($this->deleteRoleDN);

        $group->roles()->detach($role);

        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteRoleDN, $this->deleteRoleName);
        $this->showDeleteModal = false;
    }
}
