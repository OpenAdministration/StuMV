<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\GroupMailmanList;
use App\Support\MailmanListSyncStatus;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

// Lazy: asks Mailman once per enabled list, which can take a while.
#[Lazy]
class MailmanSyncStatus extends Component
{
    #[Locked]
    public string $uid;

    public function mount(string $uid): void
    {
        $realm = Community::findByOrFail('ou', $uid);
        abort_unless(auth()->user()->can('admin', $realm), 403);
        abort_if($realm->isAdminRealm(), 404);

        $this->uid = $uid;
    }

    public function placeholder(): string
    {
        return view('livewire.realm.sync-status-card', [
            'icon' => 'mail',
            'heading' => __('sync.menu_mailman'),
            'state' => 'loading',
            'text' => null,
        ])->render();
    }

    public function render(MailmanListSyncStatus $status)
    {
        $states = GroupMailmanList::where('realm', $this->uid)
            ->where('enabled', true)
            ->get()
            ->map(fn (GroupMailmanList $mapping): string => $status->for($mapping)['state']);

        $state = $states->every(fn (string $s): bool => $s === 'synced') ? 'synced' : 'pending';

        return view('livewire.realm.sync-status-card', [
            'icon' => 'mail',
            'heading' => __('sync.menu_mailman'),
            'state' => $state,
            'text' => $state === 'synced' ? __('sync.status_synced') : __('sync.status_pending'),
            'href' => route('realms.group-mailman-lists', ['realm' => $this->uid]),
        ]);
    }
}
