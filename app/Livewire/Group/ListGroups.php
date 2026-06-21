<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
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

    public function mount(Community $uid){
        $this->realm_uid = $uid->getShortCode();
    }

    public function render()
    {
        $groups = Group::query()->in(Group::dnRoot($this->realm_uid))
            ->orderBy($this->sortField, $this->sortDirection)
            ->slice($page = 1, $perPage = 10);

        return view('livewire.group.list-group', [
            'groupSlice' => $groups,
        ])->title(__('groups.list_title' ));
    }

    public function deletePrepare($uid, $cn): void
    {
        $dn = Group::dnFrom($uid, $cn);
        $this->deleteGroupDn = $dn;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        Group::query()->delete($this->deleteGroupDn);
        // reset everything to prevent a 404 modal
        unset($this->deleteGroupDn);
        Flux::modal('delete')->close();
    }

    public function close(): void
    {
        $this->showDeleteModal = false;
    }

}
