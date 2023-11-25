<?php

namespace App\Livewire\Group;

use App\Ldap\Group;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListGroups extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'name';
    #[Url]
    public string $sortDirection = 'asc';

    public string $realm_uid;

    public bool $showDeleteModal = false;

    public string $deleteGroupDn;

    public string $deleteGroupName = '';


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

    public function mount($uid){
        $this->realm_uid = $uid;
    }
    public function render()
    {
        $groups = Group::query()->in(Group::dnRoot($this->realm_uid))
            ->search('cn', $this->search)
            ->orderBy($this->sortField, $this->sortDirection)
            ->slice(1, 10)
        ;
        return view('livewire.group.crud', [
            'groupSlice' => $groups,
        ])->layout('layouts.app', ['headline' => __('Groups')]);
    }

    public function deletePrepare($uid, $cn): void
    {
        $dn = Group::dnFrom($uid, $cn);
        $this->deleteGroupDn = $dn;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        Group::query()->delete($this->deleteGroupDn);
        // reset everything to prevent a 404 modal
        unset($this->deleteGroupDn);
        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showDeleteModal = false;
    }

}