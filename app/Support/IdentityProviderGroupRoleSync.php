<?php

namespace App\Support;

use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use Illuminate\Support\Collection;

/**
 * Grants StuMV committee roles based on the "groups" claim an external OIDC
 * provider sends back, per an admin-configured mapping
 * (IdentityProviderRoleMapping: external group -> committee + role), and
 * reconciles them on every subsequent login: a role this sync itself granted
 * (RoleMembership.identity_provider_id = this provider) is revoked once its
 * mapped external group is no longer in the claim, and reactivated (until
 * cleared) if it later reappears. Revocation sets `until` to today, the same
 * "active through the end of this day" convention
 * App\Livewire\Committee\TerminateRoleMemberships uses for a manual
 * termination - isActive() (and the ldap:sync-roles command that eventually
 * removes the LDAP-side effect) treat it as inactive starting the next day.
 * A role that was already
 * there for some other reason - manually granted, or granted by a different
 * provider - is never touched, since it's never stamped with this provider's
 * id in the first place. Safe to run on every login (idempotent either way).
 */
class IdentityProviderGroupRoleSync
{
    public function apply(RealmIdentityProvider $provider, string $username, array $claims): void
    {
        $claimKey = $provider->groups_claim ?: 'groups';

        if (! array_key_exists($claimKey, $claims)) {
            // The provider didn't send a groups claim at all this login (e.g.
            // a missing scope or a transient hiccup) - treat that as "we don't
            // know", not "the user is in no groups", so a one-off missing
            // claim can never revoke every role this sync has ever granted.
            return;
        }

        $externalGroups = (array) $claims[$claimKey];
        $mappings = $provider->roleMappings()->whereIn('external_group', $externalGroups)->get();

        $this->revokeStaleGrants($provider, $username, $mappings);
        $this->grantMappedRoles($provider, $username, $mappings);
    }

    private function revokeStaleGrants(RealmIdentityProvider $provider, string $username, Collection $mappings): void
    {
        $grantedByThisProvider = RoleMembership::where('username', $username)
            ->where('identity_provider_id', $provider->id)
            ->get()
            ->filter(fn (RoleMembership $membership): bool => $membership->isActive());

        foreach ($grantedByThisProvider as $membership) {
            $stillMapped = $mappings->contains(
                fn ($mapping): bool => $mapping->committee_dn === $membership->committee_dn && $mapping->role_cn === $membership->role_cn
            );

            if (! $stillMapped) {
                $membership->update(['until' => now()->toDateString()]);
            }
        }
    }

    private function grantMappedRoles(RealmIdentityProvider $provider, string $username, Collection $mappings): void
    {
        foreach ($mappings as $mapping) {
            $membership = RoleMembership::firstOrNew([
                'role_cn' => $mapping->role_cn,
                'committee_dn' => $mapping->committee_dn,
                'username' => $username,
            ]);

            if (! $membership->exists) {
                $membership->forceFill([
                    'realm' => $provider->realm,
                    'from' => now()->toDateString(),
                    'until' => null,
                    'decided' => now()->toDateString(),
                    'comment' => __('identity_providers.auto_assigned_comment', ['provider' => $provider->name]),
                    'identity_provider_id' => $provider->id,
                ])->save();

                continue;
            }

            // Clear a scheduled/already-past expiry whenever the mapping is
            // reconfirmed - checking `until !== null` rather than
            // `! isActive()`, since a same-day revoke's `until` (today) keeps
            // isActive() true through the rest of today (see
            // revokeStaleGrants()) but should still be cleared here rather
            // than left to lapse tomorrow now that the group is back.
            if ($membership->identity_provider_id === $provider->id && $membership->until !== null) {
                $membership->update(['until' => null]);
            }
        }
    }
}
