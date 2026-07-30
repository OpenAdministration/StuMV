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
 * (RoleMembership.identity_provider_id = this provider) is revoked, effective
 * the day before this login (`until` = yesterday, so it's already inactive
 * today - the access is gone at the IdP now, not as of some future effective
 * date), once its mapped external group is no longer in the claim, and
 * reactivated (until cleared) if it later reappears. A role that was already
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
                if ($membership->from->isToday()) {
                    // "Ended the day before" would mean until < from here,
                    // which Carbon's isActive()/betweenIncluded() treats as a
                    // reversed range and reads as still active (it swaps the
                    // bounds) - a grant that never saw a single active day
                    // has no history worth keeping, so just remove it.
                    $membership->delete();
                } else {
                    $membership->update(['until' => now()->subDay()->toDateString()]);
                }
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

            // Clear a previous revoke's `until` whenever the mapping is
            // reconfirmed, regardless of isActive() - a membership revoked
            // moments ago on an earlier day is already inactive, but should
            // still reactivate here rather than staying revoked forever.
            if ($membership->identity_provider_id === $provider->id && $membership->until !== null) {
                $membership->update(['until' => null]);
            }
        }
    }
}
