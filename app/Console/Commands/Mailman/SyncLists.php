<?php

namespace App\Console\Commands\Mailman;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Support\MailmanClient;
use App\Support\MailmanDesiredMembers;
use App\Support\MailmanListSyncStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailman:sync-lists
                {realm? : The short name of the community to limit syncing to}
                {group_dn? : Limit syncing to a single mapping\'s group_dn}
                {--list= : Limit syncing to a single Mailman list_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs the active DB-driven membership of mapped LDAP groups onto their Mailman 3 mailing lists';

    public function handle(MailmanClient $mailman, MailmanDesiredMembers $desiredMembers): int
    {
        if (! config('services.mailman.api_url')) {
            $this->error('MAILMAN_API_URL is not configured - nothing to sync.');

            return self::FAILURE;
        }

        $mappings = GroupMailmanList::query()
            ->when($this->argument('realm'), fn ($query, $realm) => $query->where('realm', $realm))
            ->when($this->argument('group_dn'), fn ($query, $groupDn) => $query->where('group_dn', $groupDn))
            ->when($this->option('list'), fn ($query, $listId) => $query->where('mailman_list_id', $listId))
            ->get();

        if ($mappings->isEmpty()) {
            $this->comment('No group <-> Mailman list mappings configured.');

            return self::SUCCESS;
        }

        foreach ($mappings->reject->enabled as $mapping) {
            $this->comment("> {$mapping->mailman_list_id} is disabled, skipping");
        }
        $mappings = $mappings->filter->enabled;

        $withGroup = $mappings->filter(fn (GroupMailmanList $mapping): bool => Group::find($mapping->group_dn) !== null);

        // Desired members are resolved once per realm and rosters fetched concurrently, up front.
        $warnings = [];
        $desiredByMapping = $desiredMembers->forMany($withGroup, function (GroupMailmanList $mapping, string $message) use (&$warnings): void {
            $warnings[$mapping->id][] = $message;
        });
        $rosters = $mailman->listMembersMany($withGroup->pluck('mailman_list_id')->unique()->values()->all());

        $hadFailures = false;

        foreach ($mappings as $mapping) {
            $this->comment("> {$mapping->group_dn} -> {$mapping->mailman_list_id}");

            if (! $withGroup->contains($mapping)) {
                $this->warn("  |-> Group no longer exists, skipping: {$mapping->group_dn}");

                continue;
            }

            foreach ($warnings[$mapping->id] ?? [] as $message) {
                $this->warn("  |   |-> {$message}");
            }

            $roster = $rosters[$mapping->mailman_list_id];
            if ($roster instanceof Throwable) {
                $this->error("  |-> Could not fetch the list members: {$roster->getMessage()}");
                Log::error('Mailman listMembers failed', ['list_id' => $mapping->mailman_list_id, 'exception' => $roster->getMessage()]);
                $hadFailures = true;

                continue;
            }

            if (! $this->syncListMembers($mailman, $mapping->mailman_list_id, array_keys($desiredByMapping[$mapping->id]), $roster)) {
                $hadFailures = true;
            }

            MailmanListSyncStatus::forget($mapping);
        }

        return $hadFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $desiredEmails
     * @param  array<int, array{email: string, self_link: string, as_user: bool}>  $current
     */
    private function syncListMembers(MailmanClient $mailman, string $listId, array $desiredEmails, array $current): bool
    {
        $currentByEmail = collect($current)->keyBy('email');

        $additions = array_values(array_diff($desiredEmails, $currentByEmail->keys()->all()));
        $removals = $currentByEmail->except($desiredEmails)->values()->all();

        foreach ($removals as $member) {
            $this->comment("  |-> Remove: {$member['email']}");
        }
        $failedRemovals = $mailman->unsubscribe($listId, $removals);

        foreach ($additions as $email) {
            $this->comment("  |-> Add: $email");
        }
        $failedAdditions = $mailman->subscribe($listId, $additions);

        foreach ($failedRemovals as $email => $reason) {
            $this->error("  |   |-> Failed to remove $email: $reason");
            Log::error('Mailman unsubscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $reason]);
        }
        foreach ($failedAdditions as $email => $reason) {
            $this->error("  |   |-> Failed to add $email: $reason");
            Log::error('Mailman subscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $reason]);
        }

        return $failedRemovals === [] && $failedAdditions === [];
    }
}
