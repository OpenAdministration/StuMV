<?php

namespace App\Console\Commands\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use App\Support\MailmanClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

    public function handle(MailmanClient $mailman): int
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

        // Same DB-driven desired-membership pipeline as ldap:sync-groups
        // (role_group_relation -> role_user_relation), fetched once up
        // front for the same reason: one query instead of one per mapping.
        $memberships = RoleMembership::active()->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);
        $groupRolesByGroup = GroupMembership::all()->groupBy('group_dn');

        $hadFailures = false;

        foreach ($mappings as $mapping) {
            $this->comment("> {$mapping->group_dn} -> {$mapping->mailman_list_id}");

            if (! Group::find($mapping->group_dn)) {
                $this->warn("  |-> Group no longer exists, skipping: {$mapping->group_dn}");

                continue;
            }

            $usernamesInRealm = $memberships->where('realm', $mapping->realm)->pluck('username')->unique()->all();
            $ldapUsersByUsername = empty($usernamesInRealm)
                ? collect()
                : LdapUser::query()->in(Community::peopleDnFor($mapping->realm))->whereIn('uid', $usernamesInRealm)->get()
                    ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

            $desiredEmails = collect();

            foreach ($groupRolesByGroup->get($mapping->group_dn, collect()) as $groupRole) {
                $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                $key = $committeeDn.'|'.$roleCn;

                foreach ($membershipsByRole->get($key, collect()) as $membership) {
                    $user = $ldapUsersByUsername->get($membership->username);
                    if ($user === null) {
                        $this->warn("  |   |-> Unknown LDAP user: $membership->username");

                        continue;
                    }
                    $mail = $user->getFirstAttribute('mail');
                    if (! $mail) {
                        $this->warn("  |   |-> No mail attribute for: $membership->username");

                        continue;
                    }
                    $desiredEmails->push($mail);
                }
            }

            if (! $this->syncListMembers($mailman, $mapping->mailman_list_id, $desiredEmails->unique()->values()->all())) {
                $hadFailures = true;
            }
        }

        return $hadFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $desiredEmails
     */
    private function syncListMembers(MailmanClient $mailman, string $listId, array $desiredEmails): bool
    {
        $current = $mailman->listMembers($listId);
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
            Log::warning('Mailman unsubscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $reason]);
        }
        foreach ($failedAdditions as $email => $reason) {
            $this->error("  |   |-> Failed to add $email: $reason");
            Log::warning('Mailman subscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $reason]);
        }

        return $failedRemovals === [] && $failedAdditions === [];
    }
}
