<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\RoleMembership;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRoleMembers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    public string $deleteUsername;

    public int $deleteId;

    public bool $showOnlyActive = true;

    public bool $ready = false;

    public function mount(Community $realm, string $ou, string $cn)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;

        // Resolves and registers the "page" query-string binding now, since
        // members load lazily (wire:init) and render() otherwise wouldn't
        // touch pagination until after the initial page load.
        $this->getPage();
    }

    public function loadMembers(): void
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

    public function updatedShowOnlyActive(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        $committee = Committee::findByName($this->uid, $this->ou);
        $role = $committee?->roles()->where('cn', $this->cn)->first();

        // create/edit/delete on any membership of this role, and viewing any
        // member's profile, all resolve to the exact same checks regardless
        // of which row - computed once here (each involves an LDAP-hitting
        // ancestor walk) rather than once per row, which would otherwise
        // multiply that walk by the number of members shown.
        $isModerator = auth()->user()->can('moderator', [$committee, $community]);
        $isAdmin = auth()->user()->can('admin', [$community]);

        if (! $this->ready) {
            return view('livewire.committee.role-members', [
                'members' => collect(),
                'committee' => $committee,
                'community' => $community,
                'role' => $role,
                'userCache' => [],
                'isModerator' => $isModerator,
                'isAdmin' => $isAdmin,
                'memberStatuses' => [],
                'deleteDisplayName' => isset($this->deleteUsername) ? $this->deleteUsername : null,
            ])->title(__('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]));
        }

        $membersQuery = $role->dbMemberships();

        if ($this->showOnlyActive) {
            $membersQuery->active(today());
        }

        $members = $membersQuery->get();

        $usernames = $members->pluck('username')->unique()->filter()->all();
        $userCache = empty($usernames)
            ? []
            : User::query()->whereIn('uid', $usernames)->get()->keyBy('uid')->all();

        // The member's display name only exists in LDAP, not on the
        // RoleMembership row itself, so search/sort-by-name are applied here
        // in PHP (after $userCache is resolved) rather than as a DB query.
        $displayName = fn ($member) => $userCache[$member->username]?->getFirstAttribute('cn') ?? $member->username;

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $members = $members->filter(fn ($member) => str_contains(mb_strtolower((string) $displayName($member)), $search))->values();
        }

        $sorted = (match ($this->sortField) {
            'name' => $members->sortBy(fn ($member) => mb_strtolower((string) $displayName($member)), SORT_NATURAL, $this->sortDirection === 'desc'),
            default => $members->sortBy($this->sortField, SORT_REGULAR, $this->sortDirection === 'desc'),
        })->values();

        $perPage = 10;
        $page = $this->getPage();
        $members = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        // Whether an active membership's user is still actually in the
        // role's LDAP group ("pending" - approved here but not yet synced
        // over there) used to be resolved once per row via
        // RoleMembership::isPending(), which re-fetched the role and the
        // user fresh from LDAP and queried group membership every time -
        // fetch the role's group members once instead and check against it.
        $roleMemberDns = $role
            ? $role->members()->get()->map(fn ($u) => $u->getDn())->all()
            : [];

        $memberStatuses = [];
        foreach ($members as $member) {
            $ldapUser = $userCache[$member->username] ?? null;
            $isActive = $member->isActive();
            $memberStatuses[$member->id] = [
                'isActive' => $isActive,
                'isPending' => $isActive && $ldapUser && ! in_array($ldapUser->getDn(), $roleMemberDns, true),
            ];
        }

        return view('livewire.committee.role-members', [
            'members' => $members,
            'committee' => $committee,
            'community' => $community,
            'role' => $role,
            'userCache' => $userCache,
            'isModerator' => $isModerator,
            'isAdmin' => $isAdmin,
            'memberStatuses' => $memberStatuses,
            'deleteDisplayName' => isset($this->deleteUsername) ? ($userCache[$this->deleteUsername]?->getFirstAttribute('cn') ?? $this->deleteUsername) : null,
        ])->title(__('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]));
    }

    public function prepareDeletion($id)
    {
        $membership = RoleMembership::findOrFail($id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('delete', [$membership, $committee, $community]);

        // The row that triggered this already has its display name in
        // render()'s $userCache - store just the username here (a plain DB
        // field) and let render() resolve the name from that cache, rather
        // than an extra LDAP round-trip for a name we're about to refetch.
        $this->deleteUsername = $membership->username;
        $this->deleteId = $membership->id;
    }

    public function commitDeletion()
    {
        $membership = RoleMembership::findOrFail($this->deleteId);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('delete', [$membership, $committee, $community]);

        $membership->delete();
        $this->close();

        Flux::toast(variant: 'success', text: __('roles.message_delete_member_success'));

        return to_route('committees.roles.members', ['realm' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn]);
    }

    public function close()
    {
        Flux::modal('delete')->close();
        unset($this->deleteUsername, $this->deleteId);
    }
}
