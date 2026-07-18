<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class SyncLdap extends Component
{
    public function render()
    {
        return view('livewire.sync-ldap');
    }

    public function syncLdap()
    {
        $rolesExitCode = Artisan::call('ldap:sync-roles');
        $groupsExitCode = Artisan::call('ldap:sync-groups');

        if ($rolesExitCode === 0 && $groupsExitCode === 0) {
            Flux::toast(variant: 'success', text: __('sync.ldap_success'));
        }
    }
}
