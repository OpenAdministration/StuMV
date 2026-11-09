<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Support\LdapSyncDiff;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Lazy]
class LdapSyncStatus extends Component
{
    #[Locked]
    public string $uid;

    public function mount(string $uid): void
    {
        abort_unless(auth()->user()->can('admin', Community::findByOrFail('ou', $uid)), 403);

        $this->uid = $uid;
    }

    public function placeholder(): string
    {
        return view('livewire.realm.sync-status-card', [
            'icon' => 'list-tree',
            'heading' => __('sync.menu_ldap'),
            'state' => 'loading',
            'text' => null,
        ])->render();
    }

    public function render(LdapSyncDiff $diff)
    {
        try {
            $realm = Community::findByOrFail('ou', $this->uid);
            $state = $diff->roleChanges($realm) === [] && $diff->groupChanges($realm) === [] ? 'synced' : 'pending';
        } catch (Throwable $e) {
            Log::warning('LDAP sync status failed', ['realm' => $this->uid, 'exception' => $e->getMessage()]);
            $state = 'error';
        }

        return view('livewire.realm.sync-status-card', [
            'icon' => 'list-tree',
            'heading' => __('sync.menu_ldap'),
            'state' => $state,
            'text' => match ($state) {
                'synced' => __('sync.status_synced'),
                'pending' => __('sync.status_pending'),
                default => __('sync.status_failed'),
            },
            'ldapAction' => $state === 'pending',
        ]);
    }
}
