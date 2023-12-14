<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\RoleMembership;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

class RoleMembers extends Component
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

    public bool $showDeleteModal = false;
    public string $deleteUsername;
    public int $deleteId;

    public bool $showTerminateModal = false;
    public string $terminateUsername;
    public int $terminateId;
    public string $terminateDate;

    public function mount(Community $uid, string $ou, string $cn) : void
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
    }

    #[Title('roles.members-title')]
    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        $committee = Committee::findByName($this->uid, $this->ou);
        $role = $committee?->roles()->where('cn', $this->cn)->first();
        $members = RoleMembership::query()
            ->where('role_cn', $this->cn)
            ->where('committee_dn', $committee->getDn())
            ->where(function ($query){ $query
                ->search('username', $this->search)
                ->search('comment', $this->search);
            })->get();
        return view('livewire.committee.role-members', [
            'members' => $members,
            'committee' => $committee,
            'community' => $community,
            'role' => $role,
        ]);
    }

    public function prepareTermination(int $id): void
    {
        $membership = RoleMembership::findOrFail($id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('terminate', [$membership, $committee, $community]);

        $this->showTerminateModal = true;
        $this->terminateDate = today()->format('Y-m-d');
        $this->terminateUsername = $membership->username;
        $this->terminateId = $membership->id;
    }

    public function commitTermination()
    {
        $membership = RoleMembership::findOrFail($this->terminateId);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('terminate', [$membership, $committee, $community]);
        $this->validate(['terminateDate' => 'date:Y-m-d|after_or_equal:' . $membership->from->format('Y-m-d')]);

        $membership->until = $this->terminateDate;
        $membership->save();
        $this->close();
        return redirect()->route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn])
            ->with('message', __('roles.message_terminate_member_success'));
    }

    public function prepareDeletion($id)
    {
        $membership = RoleMembership::findOrFail($id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('delete', [$membership, $committee, $community]);

        $this->showDeleteModal = true;
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
        return redirect()->route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn])
            ->with('message', __('roles.message_delete_member_success'));
    }

    public function close()
    {
        $this->showTerminateModal = false;
        unset($this->terminateUsername, $this->terminateId, $this->terminateDate);
        $this->resetErrorBag('terminateDate');
        $this->showDeleteModal = false;
        unset($this->deleteUsername, $this->deleteId);
    }



}
