<?php

namespace App\Support;

use App\Jobs\SyncPeopleToMailmanLists;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\User as LdapUser;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model as LdapModel;

/**
 * Syncs one person's role and its groups to LDAP right away, mailing lists after the response.
 */
class RoleMembershipLdapSync
{
    /** @var array<string, array{usernames: array<string, true>, groupDns: array<string, true>}> */
    private array $pendingMailman = [];

    /**
     * @param  bool  $wasCurrent  whether the membership was current before the change
     * @return bool false if LDAP could not be updated
     */
    public function sync(RoleMembership $membership, bool $wasCurrent = false): bool
    {
        if (! $wasCurrent && ! self::isCurrent($membership)) {
            return true;
        }

        $realm = (string) $membership->realm;
        if ($realm === '') {
            return true;
        }

        try {
            $user = LdapUser::query()->in(Community::peopleDnFor($realm))->where('uid', '=', $membership->username)->first();
            $role = Role::find("cn={$membership->role_cn},{$membership->committee_dn}");
            if ($user === null || $role === null) {
                return true;
            }

            $currentRoleDns = RoleMembership::active()
                ->where('realm', $realm)
                ->where('username', $membership->username)
                ->get()
                ->map(fn (RoleMembership $m): string => strtolower("cn={$m->role_cn},{$m->committee_dn}"))
                ->unique();

            $this->setMember($role, $user->getDn(), $currentRoleDns->contains(strtolower((string) $role->getDn())));

            $groupDns = GroupMembership::where('role_dn', $role->getDn())->pluck('group_dn')->unique()->values()->all();
            foreach ($groupDns as $groupDn) {
                $group = Group::find($groupDn);
                if ($group === null) {
                    continue;
                }
                $groupRoleDns = GroupMembership::where('group_dn', $groupDn)->pluck('role_dn')->map(strtolower(...));
                $this->setMember($group, $user->getDn(), $groupRoleDns->intersect($currentRoleDns)->isNotEmpty());
            }
        } catch (LdapRecordException $e) {
            report($e);

            return false;
        } finally {
            LdapSyncState::forget($realm);
        }

        if (config('services.mailman.api_url') && $groupDns !== []) {
            $this->queueMailmanSync($realm, (string) $membership->username, $groupDns);
        }

        return true;
    }

    /**
     * Runs the collected Mailman syncs, one per realm; called after the response.
     */
    public function syncMailmanLists(): void
    {
        $pending = $this->pendingMailman;
        $this->pendingMailman = [];

        foreach ($pending as $realm => $people) {
            rescue(fn () => dispatch_sync(new SyncPeopleToMailmanLists(
                (string) $realm,
                array_keys($people['usernames']),
                array_keys($people['groupDns']),
            )), report: true);
        }
    }

    /**
     * @param  array<int, string>  $groupDns
     */
    private function queueMailmanSync(string $realm, string $username, array $groupDns): void
    {
        if ($this->pendingMailman === []) {
            app()->terminating($this->syncMailmanLists(...));
        }

        $this->pendingMailman[$realm]['usernames'][$username] = true;
        foreach ($groupDns as $groupDn) {
            $this->pendingMailman[$realm]['groupDns'][$groupDn] = true;
        }
    }

    public static function isCurrent(RoleMembership $membership): bool
    {
        return $membership->from !== null
            && $membership->from->lte(today())
            && ($membership->until === null || $membership->until->gte(today()));
    }

    // The empty placeholder keeps the entry valid once its last member is gone.
    private function setMember(LdapModel $entry, string $dn, bool $member): void
    {
        $values = $entry->getAttribute('uniqueMember') ?? [];
        $existing = collect($values)->first(fn (string $value): bool => strcasecmp($value, $dn) === 0);

        if ($member && $existing === null) {
            $entry->addAttribute('uniqueMember', $dn);
        } elseif (! $member && $existing !== null) {
            if (! in_array('', $values, true) && count($values) === 1) {
                $entry->addAttribute('uniqueMember', '');
            }
            $entry->removeAttribute('uniqueMember', $existing);
        }
    }
}
