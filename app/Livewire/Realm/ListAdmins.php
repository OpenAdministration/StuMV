<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListAdmins extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'full_name';

    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $community_name;

    public string $deleteAdminName = '';

    public string $deleteAdminUsername = '';

    public bool $ready = false;

    public function mount(Community $uid)
    {
        $this->community_name = $uid->getFirstAttribute('ou');
    }

    public function loadAdmins(): void
    {
        $this->ready = true;
    }

    public function sortBy($field): void
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

    #[Computed]
    public function community(): ?Community
    {
        return Community::findByUid($this->community_name);
    }

    public function render()
    {
        $community = $this->community();

        if (! $this->ready) {
            return view(
                'livewire.realm.list-admins', [
                    'community' => $community,
                    'realm_admins' => collect(),
                ]
            )->title(__('realms.admins_heading', [
                'name' => $community->description[0],
                'uid' => $community->ou[0],
            ]));
        }

        $admins = $this->sortByName($community?->adminsGroup()->members()->get() ?? collect());

        return view(
            'livewire.realm.list-admins', [
                'community' => $community,
                'realm_admins' => $admins,
            ]
        )->title(__('realms.admins_heading', [
            'name' => $community->description[0],
            'uid' => $community->ou[0],
        ]));
    }

    public function deletePrepare($username): void
    {
        $user = User::findByUsername($username);
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_admin', $community);
        $userIsAdmin = $community?->adminsGroup()->members()->get()->contains($user);
        if (! $userIsAdmin) {
            // check if the user to delete is an admin in this realm
            unset($this->deleteAdminUsername, $this->deleteAdminName);

            return;
        }
        $this->deleteAdminUsername = $username;
        $this->deleteAdminName = $user->getFirstAttribute('cn');
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_admin', $community);
        $admins = $community?->adminsGroup()->members();
        $user = User::findByUsername($this->deleteAdminUsername);
        $admins->detach($user);

        // reset everything to prevent a 404 modal
        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteAdminUsername, $this->deleteAdminName);
        Flux::modal('delete')->close();
    }

    /**
     * Sorted client-side (rather than via an LDAP orderBy) because the
     * production directory doesn't support the sssvlv sort control.
     */
    protected function sortByName(Collection $users): Collection
    {
        return $users
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute('cn')), SORT_NATURAL)
            ->values();
    }
}
