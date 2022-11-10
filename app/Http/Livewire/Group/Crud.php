<?php

namespace App\Http\Livewire\Group;

use App\Models\Group;
use App\Models\Realm;
use Livewire\Component;
use Livewire\WithPagination;

class Crud extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public bool $showEditModal = false;
    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public Group $editGroup;
    public Group $newGroup;
    public Group $deleteGroup;

    public string $editGroupOldName = '';
    public string $deleteGroupName = '';

    public array $rules = [];

    protected $queryString = ['search', 'sortField', 'sortDirection'];

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

    public function render()
    {
        return view('livewire.group.crud', [
            'groups' => Group::query()->search('name', $this->search)
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
            'realms' => Realm::all(),
        ])->layout('layouts.app', ['headline' => __('Groups')]);
    }

    public function edit($id): void
    {
        $this->rules = [
            'editGroup.name' => 'required',
        ];
        $this->editGroup = Group::find($id);
        $this->editGroupOldName = $this->editGroup->name;
        $this->showEditModal = true;
    }

    public function new(): void
    {
        $this->rules = [
            'newGroup.name' => 'required',
            'newGroup.realm_uid' => 'required',
        ];
        $this->newGroup = new Group();
        $this->showNewModal = true;
    }

    public function deletePrepare($id): void
    {
        $this->deleteGroup = Group::find($id);
        $this->deleteGroupName = $this->deleteGroup->name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->deleteGroup->delete();
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
        $this->editGroup->save();
        $this->showEditModal = false;
    }

    public function saveNew(): void
    {
        $this->validate();

        $groupRealm = Realm::find($this->newGroup->realm_uid);

        if (empty($groupRealm)) {
            $this->addError('newGroup.realm_uid', 'Ungültiger Realm!');
            return;
        }

        $this->newGroup->save();
        $this->showNewModal = false;
    }
}
