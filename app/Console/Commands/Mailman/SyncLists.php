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
                {group_dn? : Limit syncing to a single mapping\'s group_dn}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs the active DB-driven membership of mapped LDAP groups onto their Mailman 3 mailing lists';

    public function handle(MailmanClient $mailman): int
    {
        if (! config('services.mailman.url')) {
            $this->error('MAILMAN_URL is not configured - nothing to sync.');

            return self::FAILURE;
        }

        $mappings = GroupMailmanList::query()
            ->when($this->argument('realm'), fn ($query, $realm) => $query->where('realm', $realm))
            ->when($this->argument('group_dn'), fn ($query, $groupDn) => $query->where('group_dn', $groupDn))
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

        // Several groups can map to the same list, so the desired roster
        // has to be accumulated across all of a list's mappings before
        // syncing it - syncing per-mapping would make each pass undo the
        // previous group's subscriptions instead of unioning them.
        foreach ($mappings->groupBy('mailman_list_id') as $listId => $listMappings) {
            $desiredEmails = collect();

            foreach ($listMappings as $mapping) {
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

                $groupRoles = $groupRolesByGroup->get($mapping->group_dn, collect());

                foreach ($groupRoles as $groupRole) {
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
            }

            if (! $this->syncListMembers($mailman, $listId, $desiredEmails->unique()->values()->all())) {
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

        $additions = array_diff($desiredEmails, $currentByEmail->keys()->all());
        $removals = $currentByEmail->keys()->diff($desiredEmails)->all();

        $ok = true;

        foreach ($removals as $email) {
            $this->comment("  |-> Remove: $email");

            try {
                $mailman->unsubscribe($currentByEmail[$email]['self_link']);
            } catch (Throwable $e) {
                $ok = false;
                $this->error("  |   |-> Failed to remove $email: {$e->getMessage()}");
                Log::warning('Mailman unsubscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $e->getMessage()]);
            }
        }

        foreach ($additions as $email) {
            $this->comment("  |-> Add: $email");

            try {
                $mailman->subscribe($listId, $email);
            } catch (Throwable $e) {
                $ok = false;
                $this->error("  |   |-> Failed to add $email: {$e->getMessage()}");
                Log::warning('Mailman subscribe failed', ['list_id' => $listId, 'email' => $email, 'exception' => $e->getMessage()]);
            }
        }

        return $ok;
    }
}
