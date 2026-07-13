<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\RoleMembership;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListRoleMembers extends Component
{
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

    public function mount(Community $uid, string $ou, string $cn)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
    }

    public function loadMembers(): void
    {
        $this->ready = true;
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
            ])->title(__('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]));
        }

        $membersQuery = $role->dbMemberships();

        if ($this->showOnlyActive) {
            $membersQuery->active(today());
        }

        if ($this->search !== '') {
            $membersQuery->where('cn', $this->search);
        }

        $members = $membersQuery->get();

        $usernames = $members->pluck('username')->unique()->filter()->all();
        $userCache = empty($usernames)
            ? []
            : User::query()->whereIn('uid', $usernames)->get()->keyBy('uid')->all();

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
        ])->title(__('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]));
    }

    public function prepareDeletion($id)
    {
        $membership = RoleMembership::findOrFail($id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('delete', [$membership, $committee, $community]);

        $this->deleteUsername = User::findOrFailByUsername($membership->username)->getFirstAttribute('cn');
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

        return to_route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn]);
    }

    public function close()
    {
        Flux::modal('delete')->close();
        unset($this->deleteUsername, $this->deleteId);
    }
}
