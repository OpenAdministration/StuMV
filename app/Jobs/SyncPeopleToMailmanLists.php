<?php

namespace App\Jobs;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMailmanListGroup;
use App\Support\MailmanClient;
use App\Support\MailmanListSyncStatus;
use App\Support\MailmanRolePlan;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Subscribes/unsubscribes the given people on the enabled lists fed by the given groups.
 */
class SyncPeopleToMailmanLists
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $usernames
     * @param  array<int, string>  $groupDns
     */
    public function __construct(
        public readonly string $realm,
        public readonly array $usernames,
        public readonly array $groupDns,
    ) {}

    public function handle(MailmanClient $mailman, MailmanRolePlan $rolePlan): void
    {
        $groupDns = array_map(strtolower(...), $this->groupDns);
        $mappings = GroupMailmanList::query()
            ->with('groups')
            ->where('realm', $this->realm)
            ->where('enabled', true)
            ->get()
            ->filter(fn (GroupMailmanList $m): bool => $m->groups->contains(fn (GroupMailmanListGroup $g): bool => in_array(strtolower((string) $g->group_dn), $groupDns, true)))
            ->values();
        if ($mappings->isEmpty() || $this->usernames === []) {
            return;
        }

        $mails = LdapUser::query()->in(Community::peopleDnFor($this->realm))->whereIn('uid', $this->usernames)->get()
            ->map(fn (LdapUser $user): ?string => $user->getFirstAttribute('mail'))
            ->filter()
            ->values()
            ->all();
        if ($mails === []) {
            return;
        }

        $plans = $rolePlan->for($mappings);

        foreach (GroupMailmanListGroup::ROLES as $role) {
            $managed = $mappings->filter(fn (GroupMailmanList $m): bool => isset($plans[$m->id][$role])
                && $plans[$m->id][$role]['missing_groups'] === []
                && $m->groupDns('member') !== []);
            $listIds = $managed->pluck('mailman_list_id')->unique()->values()->all();
            $rosters = $listIds === [] ? [] : $mailman->listMembersMany($listIds, $role);

            foreach ($managed as $mapping) {
                $roster = $rosters[$mapping->mailman_list_id] ?? [];
                if ($roster instanceof Throwable) {
                    Log::error('Mailman roster fetch failed', ['list_id' => $mapping->mailman_list_id, 'role' => $role, 'exception' => $roster->getMessage()]);

                    continue;
                }

                $this->syncRole($mailman, $mapping->mailman_list_id, $role, $mails, $plans[$mapping->id][$role]['desired'], $roster);
                MailmanListSyncStatus::forget($mapping);
            }
        }
    }

    /**
     * Only the given people's entries are compared, everyone else stays as is.
     *
     * @param  array<int, string>  $mails
     * @param  array<int, string>  $desiredEmails
     * @param  array<int, array{email: string, self_link: string, as_user: bool}>  $roster
     * @param  'member'|'moderator'  $role
     */
    private function syncRole(MailmanClient $mailman, string $listId, string $role, array $mails, array $desiredEmails, array $roster): void
    {
        $desired = collect($desiredEmails)->map(strtolower(...))->flip();
        $current = collect($roster)->keyBy(fn (array $member): string => strtolower((string) $member['email']))->all();

        $additions = [];
        $removals = [];
        foreach ($mails as $mail) {
            $key = strtolower($mail);
            if ($desired->has($key) && ! isset($current[$key])) {
                $additions[] = $mail;
            } elseif (! $desired->has($key) && isset($current[$key])) {
                $removals[] = $current[$key];
            }
        }

        try {
            $failed = $role === 'member'
                ? [...$mailman->unsubscribe($listId, $removals), ...$mailman->subscribe($listId, $additions)]
                : [...$mailman->removeRole($listId, $role, $removals), ...$mailman->addRole($listId, $role, $additions)];
        } catch (Throwable $e) {
            $failed = ['*' => $e->getMessage()];
        }

        foreach ($failed as $email => $reason) {
            Log::error('Mailman people sync failed', ['list_id' => $listId, 'role' => $role, 'email' => $email, 'exception' => $reason]);
        }
    }
}
