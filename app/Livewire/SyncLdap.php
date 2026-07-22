<?php

namespace App\Livewire;

use App\Ldap\Community;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SyncLdap extends Component
{
    #[Locked]
    public string $uid;

    /**
     * $uid is passed as a plain string (not the Community model) precisely
     * so it can be #[Locked] - re-resolving and re-checking the admin
     * ability here, rather than trusting the header's @can check alone, is
     * what actually prevents a moderator of one realm from syncing another
     * realm's LDAP state via a crafted Livewire request.
     */
    public function mount(string $uid): void
    {
        $realm = Community::findByOrFail('ou', $uid);
        abort_unless(auth()->user()->can('admin', $realm), 403);

        $this->uid = $uid;
    }

    public function render()
    {
        return view('livewire.sync-ldap');
    }

    public function syncLdap()
    {
        $rolesExitCode = Artisan::call('ldap:sync-roles', ['community' => $this->uid]);
        $groupsExitCode = Artisan::call('ldap:sync-groups', ['community' => $this->uid]);

        if ($rolesExitCode === 0 && $groupsExitCode === 0) {
            Flux::toast(variant: 'success', text: __('sync.ldap_success'));
        }
    }
}
