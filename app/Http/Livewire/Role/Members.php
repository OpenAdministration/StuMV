<?php

namespace App\Http\Livewire\Role;

use App\Models\Role;
use App\Models\RoleUserRelation;
use App\Models\User;
use Livewire\Component;

class Members extends Component {
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public Role $role;

    public bool $showEditModal = false;
    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public RoleUserRelation $editRoleUserRel;
    public RoleUserRelation $newRoleUserRel;
    public RoleUserRelation $deleteRoleUserRel;

    public string $editMemberOldName = '';
    public string $deleteMemberName = '';

    public array $rules = [];

    /*
     * TODOs:
     *   - Permission check if the role belongs to a controlled realm
     *   - Add datepickers
     *   - Add check that from is earlier than until
     */

    public function mount($id) {
        $this->role = Role::findOrFail($id);
    }

    public function render() {
        return view(
            'livewire.role.members', [
                'role' => $this->role,
                'realm_members' => $this->role->committee->realm->members,
            ]
        )->layout('layouts.app', [
            'headline' => __('roles.members_heading', [
                'name' => $this->role->name,
                'committee' => $this->role->committee->name
            ])
        ]);
    }

    public function edit($id): void
    {
        $this->rules = [
            'editRoleUserRel.from' => 'required|date',
            'editRoleUserRel.until' => 'nullable|date',
        ];

        $this->editRoleUserRel = $this->role->members()->find($id);
        $this->editMemberOldName = $this->editRoleUserRel->user->full_name;

        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        $this->validate();

        if(empty(trim($this->editRoleUserRel->until))) {
            $this->editRoleUserRel->until = null;
        }

        $this->editRoleUserRel->save();
        $this->role->refresh();
        $this->showEditModal = false;
    }

    public function new(): void
    {
        $this->rules = [
            'newRoleUserRel.user_id' => 'required|integer',
            'newRoleUserRel.from' => 'required|date',
            'newRoleUserRel.until' => 'nullable|date',
        ];

        $this->newRoleUserRel = new RoleUserRelation();
        $this->showNewModal = true;
    }

    public function saveNew(): void
    {
        $this->validate();

        $newMember = User::find($this->newRoleUserRel->user_id);

        if (empty($newMember)) {
            $this->addError('newRoleUserRel.user_id', 'Benutzer nicht gefunden!');
            $this->showNewModal = false;
            return;
        }

        $userBelongsToCommitteeRealm = $newMember->realms()->where('uid', $this->role->committee->realm_uid)->exists();

        if(!$userBelongsToCommitteeRealm) {
            $this->addError('newRoleUserRel.user_id', 'Ungültiger Benutzer!');
            $this->showNewModal = false;
            return;
        }

        if(empty(trim($this->newRoleUserRel->until))) {
            $this->newRoleUserRel->until = null;
        }

        $this->role->members()->save($this->newRoleUserRel);
        $this->role->refresh();
        $this->showNewModal = false;
    }

    public function deletePrepare($id): void
    {
        $this->deleteRoleUserRel = RoleUserRelation::find($id);

        if($this->deleteRoleUserRel->role_id != $this->role->id) {
            // only allow deletes from the same role
            unset($this->deleteRoleUserRel);
            return;
        }

        $this->deleteMemberName = $this->deleteRoleUserRel->user->full_name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->deleteRoleUserRel->delete();
        $this->role->refresh();

        // reset everything to prevent a 404 modal
        unset($this->deleteRoleUserRel);
        unset($this->newRoleUserRel);
        unset($this->editRoleUserRel);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showEditModal = false;
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}
