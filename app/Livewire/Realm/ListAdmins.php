<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $community_name;

    public string $deleteAdminName = '';

    public string $deleteAdminUsername = '';

    public bool $ready = false;

    public function mount(Community $realm): void
    {
        $this->community_name = $realm->getFirstAttribute('ou');
    }

    public function loadAdmins(): void
    {
        $this->ready = true;
    }

    public function sortBy(string $field): void
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
    public function community(): Community
    {
        return Community::findOrFailByUid($this->community_name);
    }

    public function render(): View
    {
        $community = $this->community();

        // admin/remove_admin are the same check for every row (they only
        // depend on $community, never on the row) - computed once here
        // (admin hits LDAP) rather than repeatedly per row and per menu item.
        $isAdmin = \App\Models\User::current()->can('admin', $community);
        $canRemoveAdmin = \App\Models\User::current()->can('remove_admin', $community);

        if (! $this->ready) {
            return view(
                'livewire.realm.list-admins', [
                    'community' => $community,
                    'realm_admins' => collect(),
                    'isAdmin' => $isAdmin,
                    'canRemoveAdmin' => $canRemoveAdmin,
                ]
            )->title(__('realms.admins_headline'));
        }

        $admins = $community->adminsGroup()->members()->get();
        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $admins = $admins->filter(fn ($user): bool => str_contains(mb_strtolower((string) $user->getFirstAttribute('cn')), $search));
        }
        $sorted = $this->sortUsers($admins);

        $perPage = 10;
        $page = $this->getPage();
        $admins = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view(
            'livewire.realm.list-admins', [
                'community' => $community,
                'realm_admins' => $admins,
                'isAdmin' => $isAdmin,
                'canRemoveAdmin' => $canRemoveAdmin,
            ]
        )->title(__('realms.admins_headline'));
    }

    public function deletePrepare(string $username): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $user = User::query()->in($community->peopleDn())->where('uid', '=', $username)->firstOrFail();
        $this->authorize('remove_admin', $community);
        $userIsAdmin = $community->adminsGroup()->members()->get()->contains($user);
        if (! $userIsAdmin) {
            // check if the user to delete is an admin in this realm
            $this->reset('deleteAdminUsername', 'deleteAdminName');

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
        $admins = $community->adminsGroup()->members();
        $user = User::query()->in($community->peopleDn())->where('uid', '=', $this->deleteAdminUsername)->first() ?? abort(404);
        $admins->detach($user);
        $user->recordAction('realm_admin.removed', $community->getShortCode());

        // reset everything to prevent a 404 modal
        $this->close();
    }

    public function close(): void
    {
        $this->reset('deleteAdminUsername', 'deleteAdminName');
        Flux::modal('delete')->close();
    }

    /**
     * Sorted client-side (rather than via an LDAP orderBy) because the
     * production directory doesn't support the sssvlv sort control.
     *
     * @param  Collection<array-key, User>  $users
     * @return Collection<array-key, User>
     */
    protected function sortUsers(Collection $users): Collection
    {
        return $users
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();
    }
}
