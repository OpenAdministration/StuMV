<?php

namespace App\Livewire;

use App\Ldap\Community;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class Sync extends Component
{
    public function render()
    {
        return view('livewire.sync');
    }

    public function syncLdap()
    {
        Artisan::call('ldap:sync-roles');
    }
}
