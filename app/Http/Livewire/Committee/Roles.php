<?php

namespace App\Http\Livewire\Committee;

use App\Models\Committee;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Roles extends Component {

    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public Committee $committee;

    public bool $showEditModal = false;
    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public Role $editRole;
    public Role $newRole;
    public Role $deleteRole;

    public string $editRoleOldName = '';
    public string $deleteRoleName = '';

    public array $rules = [];

    protected $queryString = ['search', 'sortField', 'sortDirection'];

    /*
     * TODOs:
     *   - Keep member overview in this controller
     *     - Remove dupes from member overview
     */

    public function mount($id) {
        $this->committee = Committee::findOrFail($id);
        if(Auth::user()->cannot('view', $this->committee)) abort(403);
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
        return view(
            'livewire.committee.roles', [
                'committee' => $this->committee,
                'roles' => Role::query()->search('name', $this->search)
                    ->where('committee_id', $this->committee->id)
                    ->orderBy($this->sortField, $this->sortDirection)
                    ->paginate(10),
                'realm_members' => $this->committee->realm->members
            ]
        )->layout('layouts.app', [
            'headline' => __('committees.roles_heading', [
                'name' => $this->committee->name,
                'realm' => $this->committee->realm_uid
            ])
        ]);
    }

    public function edit($id): void
    {
        $this->rules = [
            'editRole.name' => 'required',
        ];
        $this->editRole = Role::find($id);

        if($this->editRole->committee->id != $this->committee->id) {
            // only allow edits from the same committee
            unset($this->editRole);
            return;
        }

        $this->editRoleOldName = $this->editRole->name;
        $this->showEditModal = true;
    }

    public function new(): void
    {
        $this->rules = [
            'newRole.name' => 'required',
        ];
        $this->newRole = new Role();
        $this->showNewModal = true;
    }

    public function deletePrepare($id): void
    {
        $this->deleteRole = Role::find($id);

        if($this->deleteRole->committee->id != $this->committee->id) {
            // only allow deletes from the same committee
            unset($this->deleteRole);
            return;
        }

        $this->deleteRoleName = $this->deleteRole->name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->deleteRole->delete();

        // reset everything to prevent a 404 modal
        unset($this->deleteRole);
        unset($this->newRole);
        unset($this->editRole);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showEditModal = false;
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }

    public function saveEdit(): void
    {
        $this->validate();

        $this->editRole->save();
        $this->showEditModal = false;
    }

    public function saveNew(): void
    {
        $this->validate();

        $this->committee->roles()->save($this->newRole);
        $this->showNewModal = false;
    }

}
