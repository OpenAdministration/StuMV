<?php

namespace App\Support;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Support\Collection;

/**
 * Same DB-driven desired-membership pipeline as mailman:sync-lists
 * (role_group_relation -> role_user_relation).
 */
class MailmanDesiredMembers
{
    /**
     * @return array<string, LdapUser> keyed by mail
     */
    public function for(GroupMailmanList $mapping): array
    {
        return $this->forMany(collect([$mapping]))[$mapping->id];
    }

    /**
     * Loads memberships and LDAP users once per realm instead of once per list.
     *
     * @param  Collection<int, GroupMailmanList>  $mappings
     * @param  (callable(GroupMailmanList, string): void)|null  $warn  told about members that can't be put on a list
     * @return array<int, array<string, LdapUser>> keyed by mapping id, then by mail
     */
    public function forMany(Collection $mappings, ?callable $warn = null): array
    {
        $groupRolesByGroup = GroupMembership::whereIn('group_dn', $mappings->pluck('group_dn')->unique()->all())
            ->get()
            ->groupBy('group_dn');

        $result = [];
        foreach ($mappings->groupBy('realm') as $realm => $realmMappings) {
            $memberships = RoleMembership::active()->where('realm', $realm)->get();
            $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

            $usernames = $memberships->pluck('username')->unique()->all();
            $usersByUsername = $usernames === []
                ? collect()
                : LdapUser::query()->in(Community::peopleDnFor($realm))
                    ->whereIn('uid', $usernames)
                    ->get()
                    ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

            foreach ($realmMappings as $mapping) {
                $desiredByEmail = [];
                foreach ($groupRolesByGroup->get($mapping->group_dn, collect()) as $groupRole) {
                    $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                    $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                    $key = $committeeDn.'|'.$roleCn;

                    foreach ($membershipsByRole->get($key, collect()) as $membership) {
                        $user = $usersByUsername->get($membership->username);
                        if ($user === null) {
                            $warn && $warn($mapping, "Unknown LDAP user: {$membership->username}");

                            continue;
                        }
                        $mail = $user->getFirstAttribute('mail');
                        if (! $mail) {
                            $warn && $warn($mapping, "No mail attribute for: {$membership->username}");

                            continue;
                        }
                        $desiredByEmail[$mail] = $user;
                    }
                }
                $result[$mapping->id] = $desiredByEmail;
            }
        }

        return $result;
    }
}
