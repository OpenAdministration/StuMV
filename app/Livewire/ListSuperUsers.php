<?php

namespace App\Livewire;

use App\Ldap\Community;
use App\Ldap\SuperUserGroup;
use App\Ldap\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListSuperUsers extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'cn';
    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public string $deleteAdminName = '';
    public string $deleteAdminDn = '';


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


    public function render() {
        $superGroup = SuperUserGroup::group();
        $listSuperadmins = $superGroup->members()
            //->search('cn', $this->search)
            ->get()
        ;
        return view(
            'livewire.list-super-admins', [
                'superadmins' => $listSuperadmins,
                //->orderBy($this->sortField, $this->sortDirection)
                //->paginate(10),
                // all users that aren't admins on this realm
                //'free_admins' => User::all()->except($this->community->admins()->modelKeys()),
            ]
        )->title(__('list_superusers_title'));
    }

    public function deletePrepare($username): void
    {
        $user = User::findByUsername($username);
        $userBelongsToRealm = Community::findByUid($this->community_name)?->adminsGroup()->members()->get()->contains($user);
        if(!$userBelongsToRealm) {
            // check if the user to delete is an admin in this realm
            unset($this->deleteAdminName);
            return;
        }
        $this->deleteAdminName = $username;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $admins = Community::findByUid($this->community_name)?->adminsGroup()->members();
        $user = User::findByUsername($this->deleteAdminName);
        $admins->detach($user);

        // reset everything to prevent a 404 modal
        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteAdminName);
        $this->showDeleteModal = false;
    }
}
