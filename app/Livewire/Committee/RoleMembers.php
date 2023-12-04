<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\Role;
use App\Models\RoleUserRelation;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
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
        $committee = Committee::findByName($this->uid, $this->ou);
        $members = RoleUserRelation::query()
            ->where('role_cn', $this->cn)
            ->where('committee_dn', $committee->getDn())
            ->where(function ($query){ $query
                ->search('username', $this->search)
                ->search('comment', $this->search);
            })->get();
        return view('livewire.committee.role-members', ['members' => $members]);
    }

    public function prepareTermination(int $id): void
    {
        //auth()->user()?->can()
        $membership = RoleUserRelation::findOrFail($id);
        $this->showTerminateModal = true;
        $this->terminateDate = today()->format('Y-m-d');
        $this->terminateUsername = $membership->username;
        $this->terminateId = $membership->id;
    }

    public function commitTermination()
    {
        //auth()->user()?->can()
        $membership = RoleUserRelation::findOrFail($this->terminateId);
        $this->validate(['terminateDate' => 'date:Y-m-d|after_or_equal:' . $membership->from->format('Y-m-d')]);
        $membership->until = $this->terminateDate;
        $membership->save();
        $this->close();
        return redirect()->route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn])
            ->with('message', __('roles.message_terminate_member_success'));
    }

    public function prepareDeletion($id){
        //auth()->user()?->can()
        $membership = RoleUserRelation::findOrFail($id);
        $this->showDeleteModal = true;
        $this->deleteUsername = $membership->username;
        $this->deleteId = $membership->id;
    }

    public function commitDeletion(){
        $membership = RoleUserRelation::findOrFail($this->deleteId);
        $membership->delete();
        $this->close();
        return redirect()->route('committees.roles.members', ['uid' => $this->uid, 'ou' => $this->ou, 'cn' => $this->cn])
            ->with('message', __('roles.message_delete_member_success'));
    }

    public function close(){
        $this->showTerminateModal = false;
        unset($this->terminateUsername, $this->terminateId, $this->terminateDate);
        $this->resetErrorBag('terminateDate');
        $this->showDeleteModal = false;
        unset($this->deleteUsername, $this->deleteId);
    }



}
