<?php

namespace App\Support;

use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\RealmIdentityProvider;

/**
 * Grants StuMV LDAP group membership based on the "groups" claim an external
 * OIDC provider sends back, per an admin-configured mapping
 * (IdentityProviderGroupMapping: external group -> internal group DN).
 * Unlike IdentityProviderGroupRoleSync (which persists a RoleMembership row,
 * only ever synced into LDAP by the "ldap:sync-groups" scheduled command),
 * membership here is written straight to the LDAP group's uniqueMember
 * attribute (App\Ldap\Group::members()->attach(), the same mechanism
 * NewAdmin/NewModerator use) - so it takes effect immediately, in time for
 * this very login's own OIDC id_token/userinfo "groups" claim
 * (App\Entities\IdentityEntity), which reads that same attribute live.
 * Additive only, same rationale as IdentityProviderGroupRoleSync - a group no
 * longer present at the IdP never revokes membership here; that stays a
 * manual admin action via the existing group-members UI.
 */
class IdentityProviderGroupSync
{
    public function apply(RealmIdentityProvider $provider, string $username, array $claims): void
    {
        $externalGroups = (array) ($claims[$provider->groups_claim ?: 'groups'] ?? []);

        if (empty($externalGroups)) {
            return;
        }

        $mappings = $provider->groupMappings()->whereIn('external_group', $externalGroups)->get();

        if ($mappings->isEmpty()) {
            return;
        }

        $ldapUser = LdapUser::findByUsername($username);

        if ($ldapUser === null) {
            return;
        }

        foreach ($mappings->unique('group_dn') as $mapping) {
            $group = Group::find($mapping->group_dn);

            if ($group === null || $group->members()->exists($ldapUser)) {
                continue;
            }

            $group->members()->attach($ldapUser);
        }
    }
}
