<?php

namespace App\Http\Livewire\Realm;

use App\Models\Realm;
use Livewire\Component;
use Livewire\WithPagination;

class Crud extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'uid';
    public string $sortDirection = 'asc';

    public bool $showEditModal = false;
    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public Realm $editRealm;
    public Realm $newRealm;
    public Realm $deleteRealm;

    public string $editRealmOldName = '';
    public string $deleteRealmName = '';

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
        return view('livewire.realm.crud', [
          'realms' => Realm::query()->search('uid', $this->search)
              ->orderBy($this->sortField, $this->sortDirection)
              ->paginate(10)
        ])->layout('layouts.app', ['headline' => 'Realms']);
    }

    public function edit($uid): void
    {
        $this->rules = [
            'editRealm.long_name' => 'required',
        ];
        $this->editRealm = Realm::find($uid);
        $this->editRealmOldName = $this->editRealm->long_name;
        $this->showEditModal = true;
    }

    public function new(): void
    {
        $this->rules = [
            'newRealm.uid' => 'required|min:3|max:5',
            'newRealm.long_name' => 'required',
        ];
        $this->newRealm = new Realm();
        $this->showNewModal = true;
    }

    public function deletePrepare($uid): void
    {
        $this->deleteRealm = Realm::find($uid);
        $this->deleteRealmName = $this->deleteRealm->long_name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->deleteRealm->delete();
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
        $this->editRealm->save();
        $this->showEditModal = false;
    }

    public function saveNew(): void
    {
        $this->validate();
        $this->newRealm->save();
        $this->showNewModal = false;
    }
}
