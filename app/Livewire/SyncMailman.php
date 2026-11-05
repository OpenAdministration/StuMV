<?php

namespace App\Livewire;

use App\Ldap\Community;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class SyncMailman extends Component
{
    #[Locked]
    public string $uid;

    public function mount(string $uid): void
    {
        $this->authorizeRealm($uid);
        $this->uid = $uid;
    }

    public function render()
    {
        return view('livewire.sync-mailman');
    }

    public function syncMailman(): void
    {
        $this->authorizeRealm($this->uid);
        // Large lists can take minutes; the web default of 30s would kill the sync midway.
        set_time_limit(600);

        try {
            $exitCode = Artisan::call('mailman:sync-lists', ['realm' => $this->uid]);
        } catch (Throwable $e) {
            report($e);
            $exitCode = 1;
        }

        Flux::modal('sync-mailman-confirm')->close();

        if ($exitCode === 0) {
            Flux::toast(variant: 'success', text: __('sync.mailman_success'));
        } else {
            // Artisan::call() swallows the command's own output otherwise.
            Log::error('mailman:sync-lists failed', ['realm' => $this->uid, 'output' => Artisan::output()]);
            Flux::toast(variant: 'danger', text: __('sync.mailman_failed'));
        }
    }

    private function authorizeRealm(string $uid): void
    {
        $realm = Community::findByOrFail('ou', $uid);
        abort_unless(auth()->user()->can('admin', $realm), 403);
    }
}
