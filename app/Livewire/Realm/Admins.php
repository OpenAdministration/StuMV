<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Admins extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'full_name';
    #[Url]
    public string $sortDirection = 'asc';

    public string $community_name;

    public bool $showDeleteModal = false;

    public string $deleteAdminName = '';


    public function mount($uid) {
        $this->community_name = $uid;
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

    public function community(): ?Community
    {
        return Community::findByUid($this->community_name);
    }

    public function render() {
        $admins = $this->community()?->adminsGroup()->first()?->members()->get();
        return view(
            'livewire.realm.admins', [
                'realm' => $this->community(),
                'realm_admins' => $admins,
                    //->orderBy($this->sortField, $this->sortDirection)
                    //->paginate(10),
                // all users that aren't admins on this realm
                //'free_admins' => User::all()->except($this->community->admins()->modelKeys()),
            ]
        )->layout('layouts.app', [
            'headline' => __('realms.admins_heading', [
                'name' => $this->community()->description[0],
                'uid' => $this->community()->ou[0]
            ])
        ]);
    }

    public function new(): void
    {
        $this->rules = [
            'newAdmin.id' => 'required|integer',
        ];

        $this->newAdmin = new User();
        $this->showNewModal = true;
    }

    public function saveNew(): void
    {
        $this->validate();

        $newAdmin = User::find($this->newAdmin->id);

        if (empty($newAdmin)) {
            $this->addError('newAdmin.id', 'Benutzer nicht gefunden!');
            $this->showNewModal = false;
            return;
        }

        $userBelongsToRealm = $newAdmin->admin_realms()->where('uid', $this->realm->uid)->exists();

        if($userBelongsToRealm) {
            $this->addError('newAdmin.id', 'Ungültiger Benutzer!');
            $this->showNewModal = false;
            return;
        }

        $this->realm->admins()->attach($newAdmin);
        $this->realm->refresh();
        $this->showNewModal = false;
    }

    public function deletePrepare($username): void
    {
        $user = User::findByUsername($username);
        $userBelongsToRealm = Community::findByUid($this->community_name)?->adminsGroup()->first()?->members()->get()->contains($user);
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
        $admins = Community::findByUid($this->community_name)?->adminsGroup()->first()?->members();
        $user = User::findByUsername($this->deleteAdminName);
        $admins->detach($user);

        // reset everything to prevent a 404 modal
        unset($this->deleteAdminName);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}
