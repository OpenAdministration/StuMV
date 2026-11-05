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

    public string $selected_committee_dn = '';

    public array $selected_role_dns = [];

    // Bumped on committee change so Flux rebuilds the role field and re-checks its "no results" hint.
    public int $roleFieldKey = 0;

    public function mount(Community $realm, $cn)
    {
        $this->uid = $realm->getShortCode();
        $this->group_cn = Group::findOrFail(Group::dnFrom($this->uid, $cn))->getFirstAttribute('cn');
    }

    public function updatedSelectedCommitteeDn(): void
    {
        $this->reset('selected_role_dns');
        $this->roleFieldKey++;
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
        $this->validate(['selected_role_dns' => ['required', 'array', 'min:1']]);

        $group = Group::findOrFail(Group::dnFrom($this->uid, $this->group_cn));
        $committee = Committee::fromCommunity($this->uid)->recursive()->get()
            ->first(fn (Committee $committee): bool => $committee->getDn() === $this->selected_committee_dn);
        abort_if($committee === null, 404);

        // Only roles of the chosen committee - the DNs come from the client.
        $committeeRoleDns = $committee->roles()->get()->map(fn ($role) => $role->getDn());
        abort_unless(collect($this->selected_role_dns)->diff($committeeRoleDns)->isEmpty(), 404);

        $alreadyAdded = GroupMembership::where('group_dn', $group->getDn())
            ->whereIn('role_dn', $this->selected_role_dns)
            ->exists();

        if ($alreadyAdded) {
            $this->addError('selected_role_dns', __('groups.role_already_added'));

            return;
        }

        foreach ($this->selected_role_dns as $roleDn) {
            GroupMembership::create([
                'group_dn' => $group->getDn(),
                'role_dn' => $roleDn,
            ]);
        }

        Flux::toast(variant: 'success', text: trans_choice('groups.success_role_add', count($this->selected_role_dns)));

        return to_route('realms.groups.roles', ['realm' => $this->uid, 'cn' => $this->group_cn]);
    }
}
