<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User;
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
    public string $realm_uid;
    public string $group_cn;


    public bool $showDeleteModal = false;
    public string $deleteRoleDN;

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
            ]
        );
    }


    public function deletePrepare($id): void
    {
        $this->deleteRole = Role::find($id);


        if($this->deleteRole->committee->realm->uid != $this->group->realm->uid) {
            // only allow deletes from the same realm
            unset($this->deleteRole);
            return;
        }

        $this->deleteRoleName = $this->deleteRole->name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->group->roles()->detach($this->deleteRole);
        $this->group->refresh();

        // reset everything to prevent a 404 modal
        unset($this->newRole);
        unset($this->deleteRole);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}
