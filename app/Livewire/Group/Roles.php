<?php

namespace App\Livewire\Group;

use App\Ldap\Group;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Roles extends Component {

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

    public function mount($uid, $cn) {
        $this->realm_uid = $uid;
        $this->group_cn = $cn;
        $this->group_dn = Group::dnFrom($uid, $cn);
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
        $group = Group::query()->findOrFail($this->group_dn);
        return view(
            'livewire.group.roles', [
                'group_roles' => []/*Role::join('group_role_relation', 'role.id', '=', 'group_role_relation.role_id')
                    ->join('committee', 'role.committee_id', '=', 'committee.id')
                    ->select('committee.name as committee_name', 'role.name as name', 'role.id as id')
                    ->search('role.name', $this->search)
                    ->where('group_role_relation.group_id', $this->group->id)
                    ->search('committee.name', $this->search)
                    ->where('group_role_relation.group_id', $this->group->id)
                    ->orderBy($this->sortField, $this->sortDirection)
                    ->paginate(10)*/,
                // all users that aren't admins on this realm
                'free_roles' => [] /* Role::join('committee', 'role.committee_id', '=', 'committee.id')
                    ->select('committee.name as committee_name', 'committee.realm_uid as realm_uid', 'role.name as name', 'role.id as id')
                    ->where('committee.realm_uid', '=', $this->group->realm->uid)
                    ->get()
                    ->except($this->group->roles->modelKeys()) */,
            ]
        )->layout('layouts.app', [
            'headline' => __('groups.roles_heading', [
                'name' => $group->getFirstAttribute('description'),
            ])
        ]);
    }

    public function new(): void
    {
        $this->rules = [
            'newRole.id' => 'required|integer',
        ];

        $this->newRole = new Role();
        $this->showNewModal = true;
    }

    public function saveNew(): void
    {
        $this->validate();

        $newRole = Role::find($this->newRole->id);

        if (empty($newRole)) {
            $this->addError('newRole.id', 'Benutzer nicht gefunden!');
            $this->showNewModal = false;
            return;
        }

        if($newRole->committee->realm->uid != $this->group->realm->uid) {
            $this->addError('newRole.id', 'Ungültiger Benutzer!');
            $this->showNewModal = false;
            return;
        }

        $this->group->roles()->attach($newRole);
        $this->group->refresh();
        $this->showNewModal = false;
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
