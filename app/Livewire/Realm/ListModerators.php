<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListModerators extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

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
            )->title(__('realms.mods_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $this->community_name]));
        }

        $mods = $community->moderatorsGroup()->members()->get();
        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $mods = $mods->filter(fn ($user) => str_contains(mb_strtolower((string) $user->getFirstAttribute('cn')), $search));
        }
        $sorted = $this->sortUsers($mods);

        $perPage = 10;
        $page = $this->getPage();
        $mods = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view(
            'livewire.realm.list-moderators', [
                'community' => $community,
                'realm_members' => $mods,
            ]
        )->title(__('realms.mods_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $this->community_name]));
    }

    public function deletePrepare($uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_moderator', $community);
        $user = User::findOrFailByUsername($uid);
        $userBelongsToRealm = $community->moderatorsGroup()->members()->contains($user);
        if (! $userBelongsToRealm) {
            // only allow deletes from the same realm
            return;
        }
        $this->deleteMemberUsername = $uid;
        $this->deleteMemberName = $user->getFirstAttribute('cn');
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_moderator', $community);
        $user = User::findOrFailByUsername($this->deleteMemberUsername);
        $community->moderatorsGroup()->members()->detach($user);
        $this->close();
    }

    public function close(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteMemberUsername, $this->deleteMemberName);
    }

    /**
     * Sorted client-side (rather than via an LDAP orderBy) because the
     * production directory doesn't support the sssvlv sort control.
     */
    protected function sortUsers(Collection $users): Collection
    {
        return $users
            ->sortBy(fn ($user) => mb_strtolower((string) $user->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();
    }
}
