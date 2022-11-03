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
    public Realm $editRealm;
    public array $rules = [
        'editRealm.uid' => 'required',
        'editRealm.long_name' => 'required',
    ];

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
        $this->showEditModal = true;
        $this->editRealm = Realm::find($uid);
    }

    public function close(): void
    {
        $this->showEditModal = false;
    }

    public function save(): void
    {
        $this->validate();
        if($this->editRealm->isDirty('uid')){
            $this->addError('editRealm.uid', 'Realm Kürzel kann nicht geändert werde');
            $this->reset('editRealm.uid');
        }else{
            $this->editRealm->save();
            $this->showEditModal = false;
        }
    }
}