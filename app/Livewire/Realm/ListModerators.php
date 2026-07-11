<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListModerators extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'full_name';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteMemberName = '';
    public string $deleteMemberUsername = '';
    public bool $ready = false;

    #[Locked]
    public string $community_name;

    public function mount(Community $uid): void
    {
        $this->community_name = $uid->getFirstAttribute('ou');
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function loadModerators(): void
    {
        $this->ready = true;
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->community_name);

        if (! $this->ready) {
            return view(
                'livewire.realm.list-moderators', [
                    'community' => $community,
                    'realm_members' => collect(),
                ]
            )->title(__('realms.mods_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
        }

        $mods = $community->moderatorsGroup()->members()->get();
        return view(
            'livewire.realm.list-moderators', [
                'community' => $community,
                'realm_members' => $mods,
            ]
        )->title(__('realms.mods_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
    }

    public function deletePrepare($uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_moderator', $community);
        $user = User::findOrFailByUsername($uid);
        $userBelongsToRealm = $community->moderatorsGroup()->members()->contains($user);
        if (!$userBelongsToRealm) {
            // only allow deletes from the same realm
            return;
        }
        $this->deleteMemberUsername = $uid;
        $this->deleteMemberName = $user->getFirstAttribute('cn');
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_moderator', $community);
        $user = User::findOrFailByUsername($this->deleteMemberUsername);
        $community->moderatorsGroup()->members()->detach($user);
        Flux::modal('delete')->show();
    }

    public function close(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteMemberUsername, $this->deleteMemberName);
    }
}
