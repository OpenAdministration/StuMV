<?php

namespace App\Livewire\Sync;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class SyncLdap extends Component
{
    public function render()
    {
        return view('livewire.sync-ldap');
    }

    public function sync()
    {
        Artisan::call('ldap:sync');
    }
}
