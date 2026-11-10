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
     * @param  'member'|'owner'|'moderator'  $role  whose groups to resolve
     * @return array<int, array<string, LdapUser>> keyed by mapping id, then by mail
     */
    public function forMany(Collection $mappings, ?callable $warn = null, string $role = 'member'): array
    {
        return $this->resolve($mappings, $warn, $role)['users'];
    }

    /**
     * Like for(), plus which of the mapping's groups each address comes from.
     *
     * @param  'member'|'moderator'  $role
     * @return array{users: array<string, LdapUser>, sources: array<string, array<int, string>>} keyed by mail; sources are group DNs
     */
    public function withSources(GroupMailmanList $mapping, string $role = 'member'): array
    {
        $resolved = $this->resolve(collect([$mapping]), null, $role);

        return ['users' => $resolved['users'][$mapping->id], 'sources' => $resolved['sources'][$mapping->id]];
    }

    /**
     * @param  Collection<int, GroupMailmanList>  $mappings
     * @return array{users: array<int, array<string, LdapUser>>, sources: array<int, array<string, array<int, string>>>}
     */
    private function resolve(Collection $mappings, ?callable $warn, string $role): array
    {
        $mappings->each->loadMissing('groups');
        $groupDnsByMapping = $mappings->mapWithKeys(fn (GroupMailmanList $m): array => [$m->id => $m->groupDns($role)]);

        $groupRolesByGroup = GroupMembership::whereIn('group_dn', $groupDnsByMapping->flatten()->unique()->values()->all())
            ->get()
            ->groupBy('group_dn');

        $result = [];
        $sources = [];
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
                $sourcesByEmail = [];
                $groupRoles = collect($groupDnsByMapping[$mapping->id])->flatMap(fn (string $dn) => $groupRolesByGroup->get($dn, collect()));
                foreach ($groupRoles as $groupRole) {
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
                        $sourcesByEmail[$mail][$groupRole->group_dn] = true;
                    }
                }
                $result[$mapping->id] = $desiredByEmail;
                $sources[$mapping->id] = array_map(array_keys(...), $sourcesByEmail);
            }
        }

        return ['users' => $result, 'sources' => $sources];
    }
}
