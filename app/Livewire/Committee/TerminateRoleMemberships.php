<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleMembership;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TerminateRoleMemberships extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    public array $membershipsToTerminate = [];

    public ?string $terminationDate = null;

    public function mount(Community $uid, string $ou, string $cn)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->terminationDate = today()->format('Y-m-d');
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        $committee = Committee::findByName($this->uid, $this->ou);
        $role = $committee?->roles()->where('cn', $this->cn)->first();
        $memberships = $role->dbMemberships()->active(today())->get();

        return view('livewire.committee.terminate-role-memberships', [
            'committee' => $committee,
            'role' => $role,
            'memberships' => $memberships,
        ])->title(__('roles.terminate_role_memberships_title', ['role' => $role->getFirstAttribute('description')]));
    }

    public function save()
    {
        foreach ($this->membershipsToTerminate as $m) {
            $membership = RoleMembership::findOrFail($m);
            $committee = Committee::findByName($this->uid, $this->ou);
            $community = Community::findOrFailByUid($this->uid);
            $this->authorize('terminate', [$membership, $committee, $community]);
            $this->validate(['terminationDate' => 'date:Y-m-d|after_or_equal:'.$membership->from->format('Y-m-d')]);

            $membership->until = $this->terminationDate;
            $membership->save();
        }

        Flux::toast(variant: 'success', text: __('roles.message_terminate_member_success'));

        return to_route('committees.roles.members', [
            'uid' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ]);
    }
}
