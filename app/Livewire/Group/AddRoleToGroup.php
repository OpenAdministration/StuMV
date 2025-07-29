<?php

namespace App\Livewire\Group;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use Livewire\Attributes\Locked;
use Livewire\Component;

use App\Models\GroupMembership;

class AddRoleToGroup extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $group_cn;

    public string $selected_committee_dn;
    public string $selected_role_dn;

    public function mount(Community $uid, $cn)
    {
        $this->uid = $uid->getShortCode();
        $this->group_cn = $cn;
    }

    public function render()
    {
        $committees = Committee::fromCommunity($this->uid)->recursive()->get();
        $roles = collect();
        if(!empty($this->selected_committee_dn)){
            $committee = Committee::findOrFail($this->selected_committee_dn);
            $roles = $committee->roles()->get();
        }
        return view('livewire.group.add-role-to-group', [
            'committees' => $committees,
            'roles' => $roles,
        ])->title(__('groups.roles_add_title', ['group' => $this->group_cn]));
    }

    public function save()
    {
        $group_dn = Group::findOrFail(Group::dnFrom($this->uid, $this->group_cn));
        GroupMembership::create([
            'group_dn' => $group_dn,
            'role_dn' => $this->selected_role_dn,
        ]);

        return redirect()->route('realms.groups.roles', ['uid' => $this->uid, 'cn' => $this->group_cn])
            ->with('message', __('groups.success_role_add'))
        ;
    }
}
