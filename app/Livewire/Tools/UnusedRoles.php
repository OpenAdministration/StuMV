<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use Livewire\Component;

class UnusedRoles extends Component
{
    public string $realm_uid;

    public bool $ready = false;

    public function mount(Community $uid)
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function loadUnusedRoles(): void
    {
        $this->ready = true;
    }

    public function render()
    {
        if (! $this->ready) {
            return view('livewire.tools.unused-roles', [
                'unusedCommittees' => [],
                'unusedRoles' => [],
            ])->title(__('tools.unusedRoles_headline'));
        }

        $committees = Committee::fromCommunity($this->realm_uid)->get();

        $unusedCommittees = [];
        $unusedRoles = [];

        foreach ($committees as $committee) {
            $committeeUnused = true;
            $children = $committee->descendants()->get();
            if (count($children) > 0) {
                $committeeUnused = false;
            }

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
        ])->title(__('tools.unusedRoles_headline'));
    }
}
