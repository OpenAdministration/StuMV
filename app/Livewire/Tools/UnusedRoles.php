<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use Livewire\Component;

class UnusedRoles extends Component
{
    public string $realm_uid;

    public function mount(Community $uid)
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function render()
    {
        $committees = Committee::fromCommunity($this->realm_uid)
            ->orderBy('cn')
            ->get();

        $unusedCommittees = [];
        $unusedRoles = [];

        foreach ($committees as $committee) {
            $committeeUnused = true;

            $roles = $committee->roles()->get();
            foreach ($roles as $role) {
                $memberships = $role->dbMemberships()->get();
                if (count($memberships) > 0) {
                    $committeeUnused = false;
                } else {
                    $unusedRoles[] = $role;
                }
            }

            if ($committeeUnused) {
                $unusedCommittees[] = $committee;
            }
        }

        return view('livewire.tools.unused-roles', [
            'unusedCommittees' => $unusedCommittees,
            'unusedRoles' => $unusedRoles,
        ]);
    }
}
