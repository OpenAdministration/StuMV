<?php

namespace App\Support;

use App\Console\Commands\Concerns\DiffsUniqueMembers;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Support\Collection;

/**
 * Read-only diff of a realm's roles/groups in LDAP against the memberships
 * in the database - only entries that would actually change are returned.
 */
class LdapSyncDiff
{
    use DiffsUniqueMembers;

    /**
     * Additions/removals are still raw member DNs at this point - resolved
     * to display names/links afterwards by SyncLdap,
     * since removals can't be named from RoleMembership (they're no longer
     * an active membership, that's precisely why they're being removed).
     *
     * @return array<int, array{committee: string, committee_ou: string, role: string, role_cn: string, additions: array<int, string>, removals: array<int, string>}>
     */
    public function roleChanges(Community $realm): array
    {
        $memberships = RoleMembership::active(today())->where('realm', $realm->getShortCode())->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);
        $ldapUsersByUsername = $this->ldapUsersByUsername($realm, $memberships);

        $changes = [];
        foreach (Committee::fromCommunity($realm->getShortCode())->get() as $committee) {
            foreach ($committee->roles()->get() as $role) {
                $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');
                $roleMemberships = $membershipsByRole->get($key, collect());

                $desiredDns = $roleMemberships
                    ->map(fn (RoleMembership $m) => $ldapUsersByUsername->get($m->username)?->getDn())
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $diff = $this->diffUniqueMembers($role, $desiredDns);
                if (empty($diff['additions']) && empty($diff['removals'])) {
                    continue;
                }

                $changes[] = [
                    'committee' => $committee->getFullName(),
                    'committee_ou' => $committee->getShortName(),
                    'role' => $role->getFirstAttribute('description') ?? $role->getFirstAttribute('cn'),
                    'role_cn' => $role->getFirstAttribute('cn'),
                    'additions' => $diff['additions'],
                    'removals' => $diff['removals'],
                ];
            }
        }

        return $changes;
    }

    /**
     * @return array<int, array{group: string, additions: array<int, string>, removals: array<int, string>}>
     */
    public function groupChanges(Community $realm): array
    {
        $memberships = RoleMembership::active(today())->where('realm', $realm->getShortCode())->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);
        $groupRolesByGroup = GroupMembership::all()->groupBy('group_dn');
        $ldapUsersByUsername = $this->ldapUsersByUsername($realm, $memberships);

        $changes = [];
        foreach (Group::query()->in(Group::dnRoot($realm->getShortCode()))->get() as $group) {
            $groupRoles = $groupRolesByGroup->get($group->getDn(), collect());

            $desiredDns = collect();
            foreach ($groupRoles as $groupRole) {
                $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                $key = $committeeDn.'|'.$roleCn;

                foreach ($membershipsByRole->get($key, collect()) as $membership) {
                    if ($dn = $ldapUsersByUsername->get($membership->username)?->getDn()) {
                        $desiredDns->push($dn);
                    }
                }
            }

            $diff = $this->diffUniqueMembers($group, $desiredDns->unique()->values()->all());
            if (empty($diff['additions']) && empty($diff['removals'])) {
                continue;
            }

            $changes[] = [
                'group' => $group->getFirstAttribute('cn'),
                'additions' => $diff['additions'],
                'removals' => $diff['removals'],
            ];
        }

        return $changes;
    }

    /**
     * @param  Collection<int, RoleMembership>  $memberships
     * @return Collection<string, LdapUser>
     */
    private function ldapUsersByUsername(Community $realm, Collection $memberships): Collection
    {
        $usernames = $memberships->pluck('username')->unique()->all();

        return empty($usernames)
            ? collect()
            : LdapUser::query()->in($realm->peopleDn())->whereIn('uid', $usernames)->get()
                ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));
    }
}
