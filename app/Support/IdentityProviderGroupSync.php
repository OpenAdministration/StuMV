<?php

namespace App\Support;

use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\IdentityProviderGroupGrant;
use App\Models\RealmIdentityProvider;
use Illuminate\Support\Collection;

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
 *
 * Reconciled on every login via App\Models\IdentityProviderGroupGrant, since
 * LDAP group membership itself carries no metadata to tell "this sync added
 * them" apart from "they were already a member for some other reason" (a
 * manual admin action, or the independent role-derived ldap:sync-groups
 * path). A membership only gets a grant row - and therefore only becomes a
 * candidate for this sync to later detach - if this sync was the one that
 * actually attached it; anything else is left alone entirely, on both the
 * grant and the revoke side.
 */
class IdentityProviderGroupSync
{
    public function apply(RealmIdentityProvider $provider, string $username, array $claims): void
    {
        $claimKey = $provider->groups_claim ?: 'groups';

        if (! array_key_exists($claimKey, $claims)) {
            // See IdentityProviderGroupRoleSync::apply() - a claim that's
            // entirely absent this login is "unknown", not "empty", and must
            // never revoke every group membership this sync has granted.
            return;
        }

        $ldapUser = LdapUser::findByUsername($username);

        if ($ldapUser === null) {
            return;
        }

        $externalGroups = (array) $claims[$claimKey];
        $desiredGroupDns = $provider->groupMappings()
            ->whereIn('external_group', $externalGroups)
            ->get()
            ->pluck('group_dn')
            ->unique();

        $this->revokeStaleGrants($provider, $username, $ldapUser, $desiredGroupDns);
        $this->grantMappedGroups($provider, $username, $ldapUser, $desiredGroupDns);
    }

    private function revokeStaleGrants(RealmIdentityProvider $provider, string $username, LdapUser $ldapUser, Collection $desiredGroupDns): void
    {
        $staleGrants = IdentityProviderGroupGrant::where('provider_id', $provider->id)
            ->where('username', $username)
            ->whereNotIn('group_dn', $desiredGroupDns)
            ->get();

        foreach ($staleGrants as $grant) {
            $group = Group::find($grant->group_dn);

            if ($group !== null && $group->members()->exists($ldapUser)) {
                $group->members()->detach($ldapUser);
            }

            $grant->delete();
        }
    }

    private function grantMappedGroups(RealmIdentityProvider $provider, string $username, LdapUser $ldapUser, Collection $desiredGroupDns): void
    {
        foreach ($desiredGroupDns as $groupDn) {
            $group = Group::find($groupDn);

            if ($group === null) {
                continue;
            }

            if ($group->members()->exists($ldapUser)) {
                // Already a member for some other reason - manual admin
                // action, the independent role-derived ldap:sync-groups path,
                // or a grant this sync already recorded on an earlier login.
                // Never (re-)claim a membership as our own that we didn't
                // ourselves just establish, so a later revoke can't remove a
                // membership this sync isn't actually responsible for.
                continue;
            }

            $group->members()->attach($ldapUser);

            IdentityProviderGroupGrant::firstOrCreate([
                'provider_id' => $provider->id,
                'username' => $username,
                'group_dn' => $groupDn,
            ]);
        }
    }
}
