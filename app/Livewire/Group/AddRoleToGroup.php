<?php

namespace App\Livewire\Group;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMembership;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddRoleToGroup extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $group_cn;

    public string $selected_committee_dn;

    public string $selected_role_dn;

    public function mount(Community $realm, $cn)
    {
        $this->uid = $realm->getShortCode();
        $this->group_cn = $cn;
    }

    public function render()
    {
        $committees = Committee::fromCommunity($this->uid)->recursive()->get();
        $roles = collect();
        if (! empty($this->selected_committee_dn)) {
            $committee = Committee::findOrFail($this->selected_committee_dn);
            $groupDn = Group::dnFrom($this->uid, $this->group_cn);
            $addedRoleDns = GroupMembership::where('group_dn', $groupDn)->pluck('role_dn');
            $roles = $committee->roles()->get()
                ->filter(fn ($role) => ! $addedRoleDns->contains($role->getDn()));
        }

        return view('livewire.group.add-role-to-group', [
            'committees' => $committees,
            'roles' => $roles,
        ])->title(__('groups.roles_add_title', ['group' => $this->group_cn]));
    }

    public function save()
    {
        $group = Group::findOrFail(Group::dnFrom($this->uid, $this->group_cn));

        $alreadyAdded = GroupMembership::where('group_dn', $group->getDn())
            ->where('role_dn', $this->selected_role_dn)
            ->exists();

        if ($alreadyAdded) {
            $this->addError('selected_role_dn', __('groups.role_already_added'));

            return;
        }

        GroupMembership::create([
            'group_dn' => $group->getDn(),
            'role_dn' => $this->selected_role_dn,
        ]);

        Flux::toast(variant: 'success', text: __('groups.success_role_add'));

        return to_route('realms.groups.roles', ['realm' => $this->uid, 'cn' => $this->group_cn]);
    }
}
