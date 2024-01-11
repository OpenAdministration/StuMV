<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListAdmins extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'full_name';
    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $community_name;

    public bool $showDeleteModal = false;

    public string $deleteAdminName = '';


    public function mount(Community $uid) {
        $this->community_name = $uid->getFirstAttribute('ou');
    }

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

    #[Computed]
    public function community(): ?Community
    {
        return Community::findByUid($this->community_name);
    }

    public function render() {
        $admins = $this->community()?->adminsGroup()->members()->get();
        return view(
            'livewire.realm.list-admins', [
                'community' => $this->community(),
                'realm_admins' => $admins,
                //->orderBy($this->sortField, $this->sortDirection)
                //->paginate(10),
                // all users that aren't admins on this realm
                //'free_admins' => User::all()->except($this->community->admins()->modelKeys()),
            ]
        )->title(__('realms.admins_heading', [
            'name' => $this->community()->description[0],
            'uid' => $this->community()->ou[0]
        ]));
    }

    public function deletePrepare($username): void
    {
        $user = User::findByUsername($username);
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_admin', $community);
        $userIsAdmin = $community?->adminsGroup()->members()->get()->contains($user);
        if(!$userIsAdmin) {
            // check if the user to delete is an admin in this realm
            unset($this->deleteAdminName);
            return;
        }
        $this->deleteAdminName = $username;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_admin', $community);
        $admins = $community?->adminsGroup()->members();
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
