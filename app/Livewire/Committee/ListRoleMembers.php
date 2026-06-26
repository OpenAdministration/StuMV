<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\RoleMembership;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
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

    public function mount(Community $uid, string $ou, string $cn)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        $committee = Committee::findByName($this->uid, $this->ou);
        $role = $committee?->roles()->where('cn', $this->cn)->first();
        $membersQuery = $role->dbMemberships();
        
        if ($this->showOnlyActive) {
            $membersQuery->active(today());
        }

        if ($this->search !== "") {
            $membersQuery->where('cn', $this->search);
        }
        
        $members = $membersQuery->get();

        return view('livewire.committee.role-members', [
            'members' => $members,
            'committee' => $committee,
            'community' => $community,
            'role' => $role,
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
        return redirect()->route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn]);
    }

    public function close()
    {
        $this->showDeleteModal = false;
        unset($this->deleteUsername, $this->deleteId);
    }
}
