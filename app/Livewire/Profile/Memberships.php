<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use App\Ldap\Role;
use Livewire\Component;

use App\Models\RoleMembership;

class Memberships extends Component
{
    public $showOnlyActive = true;

    public function render()
    {
        $query = RoleMembership::where('username', auth()->user()->username);
        if ($this->showOnlyActive) {
            $query->whereNull('until');
        }
        $roleMemberships = $query->get();
        $memberships = [];
        foreach ($roleMemberships as $row) {
            $role = Role::findOrFail('cn=' . $row->role_cn . ',' . $row->committee_dn);
            array_push($memberships, [
                'role' => $role,
                'from' => $row->from,
                'until' => $row->until,
                'decided' => $row->decided,
                'comment' => $row->comment,
            ]);
        }

        return view('livewire.profile.memberships', [
            'memberships' => $memberships,
        ])->title(__('Profile'));
    }
}
