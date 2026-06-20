<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use Livewire\Component;

class UnusedRoles extends Component
{
    public string $realm_uid;
    public string $tab = 'roles';
    public bool $ready = false;
    public array $unusedCommittees = [];
    public array $unusedRoles = [];

    public function mount(Community $uid)
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function loadData(): void
    {
        if ($this->ready) {
            return;
        }

        $committees = Committee::fromCommunity($this->realm_uid)
            ->orderBy('cn')
            ->get();

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

        $this->unusedCommittees = $unusedCommittees;
        $this->unusedRoles = $unusedRoles;
        $this->ready = true;
    }

    public function render()
    {
        return view('livewire.tools.unused-roles');
    }
}
