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

    public bool $showDeleteModal = false;

    #[Locked]
    public string $uid;
    #[Locked]
    public string $ou;
    #[Locked]
    public string $cn;

    public string $deleteUsername;
    public int $deleteId;

    public string $terminateUsername;
    public string $terminateId;
    #[Validate('date')]
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
            ->get();
        return view('livewire.committee.role-members', ['members' => $members]);
    }


}
