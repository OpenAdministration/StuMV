<?php

namespace App\Livewire;

use App\Ldap\Community;
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
        Artisan::call('ldap:sync-roles');
        Artisan::call('ldap:sync-groups');
    }
}
