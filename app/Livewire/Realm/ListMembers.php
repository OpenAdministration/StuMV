<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use App\Support\AccountPurger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListMembers extends Component
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

    public string $community_name;

    public function mount(Community $realm): void
    {
        $this->community_name = $realm->getFirstAttribute('ou');
    }

    public function loadMembers(): void
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

    public function render(): View
    {
        $community = Community::findOrFailByUid($this->community_name);

        // admin/moderator/remove_member are the same check for every row on
        // this page (they only depend on $community, never on the row's
        // member) - computed once here (admin/moderator each hit LDAP)
        // rather than repeatedly per row and per menu item.
        $isAdmin = \App\Models\User::current()->can('admin', $community);
        $isModerator = \App\Models\User::current()->can('moderator', $community);
        $canRemoveMember = \App\Models\User::current()->can('remove_member', $community);

        // Username and last login are admin-only columns, so are their sort orders.
        $sortField = in_array($this->sortField, $isAdmin ? ['cn', 'uid', 'pwdLastSuccess'] : ['cn'], true) ? $this->sortField : 'cn';

        if (! $this->ready) {
            return view(
                'livewire.realm.members', [
                    'realm_members' => collect(),
                    'community' => $community,
                    'isAdmin' => $isAdmin,
                    'isModerator' => $isModerator,
                    'canRemoveMember' => $canRemoveMember,
                ]
            )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
        }

        // pwdLastSuccess is operational and only fetched for admins, who see the column.
        $members = User::query()->in($community->peopleDn())
            ->select($isAdmin ? ['*', 'pwdLastSuccess'] : ['*'])
            ->get();

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $members = $members->filter(fn ($user): bool => str_contains(mb_strtolower((string) $user->getFirstAttribute('cn')), $search)
                || str_contains(mb_strtolower((string) $user->getFirstAttribute('uid')), $search));
        }

        $sorted = $members
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute($sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $perPage = 10;
        $page = $this->getPage();
        $members = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view(
            'livewire.realm.members', [
                'realm_members' => $members,
                'community' => $community,
                'isAdmin' => $isAdmin,
                'isModerator' => $isModerator,
                'canRemoveMember' => $canRemoveMember,
            ]
        )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
    }

    public function removePrepare(string $uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_member', $community);
        // Only allow deletes of accounts actually located under this realm.
        $user = User::query()->in($community->peopleDn())->where('uid', '=', $uid)->first();
        if (! $user) {
            return;
        }
        $this->deleteMemberName = $user->getFirstAttribute('cn');
        $this->deleteMemberUsername = $uid;
        Flux::modal('remove')->show();
    }

    public function removeCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_member', $community);
        // Deleting the account is what "removing a member" means now -
        // membership is the physical location itself, not a group entry.
        AccountPurger::purge($community, $this->deleteMemberUsername);
        Flux::modal('remove')->close();
    }

    public function close(): void
    {
        Flux::modals()->close();
        $this->reset('deleteMemberName', 'deleteMemberUsername');
    }
}
