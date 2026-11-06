<?php

namespace App\Support;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;

/**
 * Same DB-driven desired-membership pipeline as mailman:sync-lists
 * (role_group_relation -> role_user_relation), scoped to one mapping.
 */
class MailmanDesiredMembers
{
    /**
     * @return array<string, LdapUser> keyed by mail
     */
    public function for(GroupMailmanList $mapping): array
    {
        $memberships = RoleMembership::active()->where('realm', $mapping->realm)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

        $usersByUsername = LdapUser::query()->in(Community::peopleDnFor($mapping->realm))
            ->whereIn('uid', $memberships->pluck('username')->unique()->all())
            ->get()
            ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

        $desiredByEmail = [];
        foreach (GroupMembership::where('group_dn', $mapping->group_dn)->get() as $groupRole) {
            $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
            $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
            $key = $committeeDn.'|'.$roleCn;

            foreach ($membershipsByRole->get($key, collect()) as $membership) {
                $user = $usersByUsername->get($membership->username);
                $mail = $user?->getFirstAttribute('mail');
                if ($mail) {
                    $desiredByEmail[$mail] = $user;
                }
            }
        }

        return $desiredByEmail;
    }
}
