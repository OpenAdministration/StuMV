<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRealms extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'uid';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;
    public string $deleteRealmName = '';

    public array $rules = [];

    //protected array $queryString = ['search', 'sortField', 'sortDirection'];



    public function sortBy($field): void
    {
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
        $realmsQuerry = Community::query();
        if(!empty($this->search)){
            $realmsQuerry = $realmsQuerry
                ->search('ou', $this->search)
                ->search('description', $this->search)
            ;
        }
        // fixme
        $slice = $realmsQuerry->slice(1, 10, $this->sortField, $this->sortDirection);

        return view('livewire.realm.index', [
          'realmSlice' => $slice
        ])->layout('layouts.app', ['headline' => 'Realms']);
    }

    public function deletePrepare($uid): void
    {
        $this->deleteRealmName = $uid;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->deleteRealmName);
        $community->delete(recursive: true);
        // reset everything to prevent a 404 modal
        unset($this->deleteRealmName);
        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showDeleteModal = false;
    }


}
