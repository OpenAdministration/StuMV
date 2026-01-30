<?php

namespace App\Livewire\Sync;

use App\Ldap\Community;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class SyncLdap extends Component
{
    public string $uid;

    public function mount($uid)
    {
        $this->uid = $uid;
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        return view('livewire.sync.sync-ldap', [
            'community' => $community,
        ]);
    }

    public function sync()
    {
        Artisan::call('ldap:sync-roles');
    }
}
