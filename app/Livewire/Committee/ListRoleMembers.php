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

        if (! $this->ready) {
            return view('livewire.committee.role-members', [
                'members' => collect(),
                'committee' => $committee,
                'community' => $community,
                'role' => $role,
                'userCache' => [],
            ])->title(__('roles.members-title', ['name' => $this->cn]));
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

        return view('livewire.committee.role-members', [
            'members' => $members,
            'committee' => $committee,
            'community' => $community,
            'role' => $role,
            'userCache' => $userCache,
        ])->title(__('roles.members-title', ['name' => $this->cn]));
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
