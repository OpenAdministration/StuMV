<?php

namespace App\Console\Commands\Mailman;

use App\Models\GroupMailmanList;
use App\Models\GroupMailmanListGroup;
use App\Support\MailmanClient;
use App\Support\MailmanListSyncStatus;
use App\Support\MailmanRolePlan;
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
                {group_dn? : Limit syncing to lists that use this group in any role}
                {--list= : Limit syncing to a single Mailman list_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs the active DB-driven membership of mapped LDAP groups onto the members and moderators of their Mailman 3 mailing lists';

    public function handle(MailmanClient $mailman, MailmanRolePlan $rolePlan): int
    {
        if (! config('services.mailman.api_url')) {
            $this->error('MAILMAN_API_URL is not configured - nothing to sync.');

            return self::FAILURE;
        }

        $mappings = GroupMailmanList::query()
            ->with('groups')
            ->when($this->argument('realm'), fn ($query, $realm) => $query->where('realm', $realm))
            ->when($this->argument('group_dn'), fn ($query, $groupDn) => $query->whereHas('groups', fn ($groups) => $groups->where('group_dn', $groupDn)))
            ->when($this->option('list'), fn ($query, $listId) => $query->where('mailman_list_id', $listId))
            ->get();

        if ($mappings->isEmpty()) {
            $this->comment('No group <-> Mailman list mappings configured.');

            return self::SUCCESS;
        }

        foreach ($mappings->reject->enabled as $mapping) {
            $this->comment("> {$mapping->mailman_list_id} is disabled, skipping");
        }
        $mappings = $mappings->filter->enabled->values();

        // Desired people are resolved once per realm and rosters fetched concurrently, up front.
        $warnings = [];
        $plans = $rolePlan->for($mappings, function (GroupMailmanList $mapping, string $message) use (&$warnings): void {
            $warnings[$mapping->id][$message] = true;
        });
        $rosters = [];
        foreach (GroupMailmanListGroup::ROLES as $role) {
            $listIds = $mappings
                ->filter(fn (GroupMailmanList $m): bool => isset($plans[$m->id][$role])
                    && $plans[$m->id][$role]['missing_groups'] === []
                    && $m->groupDns('member') !== [])
                ->pluck('mailman_list_id')->unique()->values()->all();
            $rosters[$role] = $listIds === [] ? [] : $mailman->listMembersMany($listIds, $role);
        }

        $hadFailures = false;

        foreach ($mappings as $mapping) {
            $this->comment("> {$mapping->mailman_list_id}");

            foreach (array_keys($warnings[$mapping->id] ?? []) as $message) {
                $this->warn("  |   |-> {$message}");
            }

            foreach ($plans[$mapping->id] ?? [] as $role => $plan) {
                if ($role === 'member' && $mapping->groupDns('member') === []) {
                    $this->warn('  |-> member: no member group assigned, skipping');

                    continue;
                }

                if ($plan['missing_groups'] !== []) {
                    $this->warn("  |-> {$role}: group no longer exists, skipping: ".implode(', ', $plan['missing_groups']));

                    continue;
                }

                $roster = $rosters[$role][$mapping->mailman_list_id];
                if ($roster instanceof Throwable) {
                    $this->error("  |-> {$role}: could not fetch the list's {$role}s: {$roster->getMessage()}");
                    Log::error('Mailman roster fetch failed', ['list_id' => $mapping->mailman_list_id, 'role' => $role, 'exception' => $roster->getMessage()]);
                    $hadFailures = true;

                    continue;
                }

                $ok = $role === 'member'
                    ? $this->syncListMembers($mailman, $mapping->mailman_list_id, $plan['desired'], $roster)
                    : $this->syncRole($mailman, $mapping->mailman_list_id, $role, $plan['desired'], $roster);
                $hadFailures = $hadFailures || ! $ok;
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

    /**
     * Moderators, compared case-insensitively.
     *
     * @param  'moderator'  $role
     * @param  array<int, string>  $desiredEmails
     * @param  array<int, array{email: string, self_link: string, as_user: bool}>  $current
     */
    private function syncRole(MailmanClient $mailman, string $listId, string $role, array $desiredEmails, array $current): bool
    {
        $desiredByLower = collect($desiredEmails)->keyBy(fn (string $email): string => strtolower($email));
        $currentByLower = collect($current)->keyBy(fn (array $member): string => strtolower($member['email']));
        $additions = $desiredByLower->diffKeys($currentByLower)->values()->all();
        $removals = $currentByLower->diffKeys($desiredByLower)->values()->all();

        foreach ($additions as $email) {
            $this->comment("  |-> Add {$role}: {$email}");
        }
        $failedAdditions = $mailman->addRole($listId, $role, $additions);

        foreach ($removals as $member) {
            $this->comment("  |-> Remove {$role}: {$member['email']}");
        }
        $failedRemovals = $mailman->removeRole($listId, $role, $removals);

        foreach ($failedAdditions as $email => $reason) {
            $this->error("  |   |-> Failed to add {$role} {$email}: {$reason}");
            Log::error('Mailman add role failed', ['list_id' => $listId, 'role' => $role, 'email' => $email, 'exception' => $reason]);
        }
        foreach ($failedRemovals as $email => $reason) {
            $this->error("  |   |-> Failed to remove {$role} {$email}: {$reason}");
            Log::error('Mailman remove role failed', ['list_id' => $listId, 'role' => $role, 'email' => $email, 'exception' => $reason]);
        }

        return $failedAdditions === [] && $failedRemovals === [];
    }
}
