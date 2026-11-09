<?php

namespace App\Support;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailmanListSyncStatus
{
    public function __construct(
        private readonly MailmanClient $mailman,
        private readonly MailmanDesiredMembers $desiredMembers,
    ) {}

    /**
     * @return array{state: 'synced'|'pending'|'stale'|'error', text: string}
     */
    public function for(GroupMailmanList $mapping): array
    {
        if (! Group::find($mapping->group_dn)) {
            return ['state' => 'error', 'text' => __('group_mailman_lists.group_missing')];
        }

        try {
            $actual = array_column($this->mailman->listMembers($mapping->mailman_list_id), 'email');
        } catch (Throwable $e) {
            Log::warning('Mailman listMembers failed', ['list_id' => $mapping->mailman_list_id, 'exception' => $e->getMessage()]);

            return ['state' => 'error', 'text' => __('group_mailman_lists.status_fetch_failed')];
        }

        $desired = array_keys($this->desiredMembers->for($mapping));
        $pending = count(array_diff($desired, $actual));
        $stale = count(array_diff($actual, $desired));

        if ($pending === 0 && $stale === 0) {
            return ['state' => 'synced', 'text' => __('group_mailman_lists.status_synced')];
        }

        $problems = array_filter([
            $pending > 0 ? __('group_mailman_lists.status_pending') : null,
            $stale > 0 ? __('group_mailman_lists.status_stale') : null,
        ]);

        // Someone still on the list who no longer should be is worse than someone missing.
        return ['state' => $stale > 0 ? 'stale' : 'pending', 'text' => implode(', ', $problems)];
    }
}
