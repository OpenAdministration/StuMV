<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListMembers extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'full_name';
    #[Url]
    public string $sortDirection = 'asc';

    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public string $deleteMemberName = '';

    public string $community_name;

    public function mount(Community $uid): void
    {
        $this->community_name = $uid->getFirstAttribute('ou');
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
        $community = Community::findOrFailByUid($this->community_name);
        $members = $community->membersGroup()->members()->get();
        return view(
            'livewire.realm.members', [
                'realm_members' => $members,
                'community' => $community
            ]
        );
    }

    public function deletePrepare($uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_member', $community);
        $userBelongsToRealm = $community->membersGroup()->members()->whereEquals('uid', $uid)->get();
        if(!$userBelongsToRealm) {
            // only allow deletes from the same realm
            return;
        }
        $this->deleteMemberName = $uid;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_member', $community);
        $user = User::findOrFailByUsername($this->deleteMemberName);
        $community->membersGroup()->members()->detach($user);
        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}
