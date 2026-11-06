<?php

namespace App\Livewire\Mailman;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Support\MailmanClient;
use App\Support\MailmanDesiredMembers;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

// Lazy: every row asks Mailman on its own, so the overview itself renders immediately.
#[Lazy]
class MailmanListStatus extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public int $mappingId;

    public function placeholder(): string
    {
        return '<div><flux:icon.loading variant="mini" class="text-zinc-400" /></div>';
    }

    public function render(MailmanClient $mailman, MailmanDesiredMembers $desiredMembers)
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($this->mappingId);

        return view('livewire.mailman.mailman-list-status', $this->status($mapping, $mailman, $desiredMembers));
    }

    /**
     * @return array{state: string, text: string}
     */
    private function status(GroupMailmanList $mapping, MailmanClient $mailman, MailmanDesiredMembers $desiredMembers): array
    {
        if (! Group::find($mapping->group_dn)) {
            return ['state' => 'error', 'text' => __('group_mailman_lists.group_missing')];
        }

        try {
            $actual = array_column($mailman->listMembers($mapping->mailman_list_id), 'email');
        } catch (Throwable $e) {
            Log::warning('Mailman listMembers failed', ['list_id' => $mapping->mailman_list_id, 'exception' => $e->getMessage()]);

            return ['state' => 'error', 'text' => __('group_mailman_lists.status_fetch_failed')];
        }

        $desired = array_keys($desiredMembers->for($mapping));
        $pending = count(array_diff($desired, $actual));
        $stale = count(array_diff($actual, $desired));

        if ($pending === 0 && $stale === 0) {
            return ['state' => 'synced', 'text' => trans_choice('group_mailman_lists.status_synced', count($desired))];
        }

        // Someone still on the list who no longer should be is worse than someone missing.
        return ['state' => $stale > 0 ? 'stale' : 'pending', 'text' => __('group_mailman_lists.status_out_of_sync', ['pending' => $pending, 'stale' => $stale])];
    }
}
