<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\GroupMembership;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListGroupMembers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $group_cn;

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

    public function mount(Community $realm, $cn): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
        $this->group_cn = $cn;
    }

    public function render()
    {
        $group = Group::findOrFail(Group::dnFrom($this->realm_uid, $this->group_cn));

        // A group's members are derived from the active memberships of every
        // role assigned to it - fetch all of those roles and memberships in
        // batch, rather than walking role-by-role per row.
        $roleDns = GroupMembership::query()->where('group_dn', $group->getDn())->pluck('role_dn')->unique()->all();
        $roles = empty($roleDns) ? collect() : Role::query()->findMany($roleDns);

        $desiredMemberships = collect();
        foreach ($roles as $role) {
            $desiredMemberships = $desiredMemberships->concat($role->dbMemberships()->active(today())->get());
        }

        $usernames = $desiredMemberships->pluck('username')->unique()->filter()->all();
        $ldapUsersByUsername = empty($usernames)
            ? collect()
            : User::query()->whereIn('uid', $usernames)->get()->keyBy(fn (User $user) => $user->getFirstAttribute('uid'));

        // The actual LDAP state (what "ldap:sync-groups" last wrote), so we
        // can flag members that are desired but not yet synced ("pending")
        // and members that are synced but no longer desired ("stale" - due
        // for removal on the next sync run).
        $actualMembers = $group->members()->get();
        $actualDns = array_flip($actualMembers->map(fn ($user) => $user->getDn())->all());

        $rows = collect();
        $seenDns = [];
        foreach ($desiredMemberships as $membership) {
            $ldapUser = $ldapUsersByUsername->get($membership->username);
            if (! $ldapUser || isset($seenDns[$ldapUser->getDn()])) {
                continue;
            }
            $seenDns[$ldapUser->getDn()] = true;
            $rows->push([
                'user' => $ldapUser,
                'status' => isset($actualDns[$ldapUser->getDn()]) ? 'synced' : 'pending',
            ]);
        }
        foreach ($actualMembers as $actualUser) {
            if (! isset($seenDns[$actualUser->getDn()])) {
                $rows->push(['user' => $actualUser, 'status' => 'stale']);
            }
        }

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower((string) $row['user']->getFirstAttribute('cn')), $search)
                || str_contains(mb_strtolower((string) $row['user']->getFirstAttribute('uid')), $search));
        }

        $sorted = $rows
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['user']->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $perPage = 10;
        $page = $this->getPage();
        $members = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view('livewire.group.members', [
            'members' => $members,
            'group' => $group,
        ])->title(__('groups.members_title', ['name' => $this->group_cn]));
    }
}
