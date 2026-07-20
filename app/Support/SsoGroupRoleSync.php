<?php

namespace App\Support;

use App\Models\RealmSsoProvider;
use App\Models\RoleMembership;

/**
 * Grants StuMV committee roles based on the "groups" claim an external OIDC
 * provider sends back, per an admin-configured mapping
 * (SsoProviderRoleMapping: external group -> committee + role). Additive
 * only - a group no longer present at the IdP is never used to revoke a
 * role; that stays a manual admin action via the existing role-membership
 * UI. Idempotent (RoleMembership has no DB unique constraint of its own -
 * see the other membership-creation call sites), so it is safe to run on
 * every login.
 */
class SsoGroupRoleSync
{
    public function apply(RealmSsoProvider $provider, string $username, array $claims): void
    {
        $externalGroups = (array) ($claims[$provider->groups_claim ?: 'groups'] ?? []);

        if (empty($externalGroups)) {
            return;
        }

        $mappings = $provider->roleMappings()->whereIn('external_group', $externalGroups)->get();

        foreach ($mappings as $mapping) {
            RoleMembership::firstOrCreate([
                'role_cn' => $mapping->role_cn,
                'committee_dn' => $mapping->committee_dn,
                'username' => $username,
            ], [
                'realm' => $provider->realm,
                'from' => now()->toDateString(),
                'until' => null,
                'decided' => now()->toDateString(),
                'comment' => __('sso_providers.auto_assigned_comment', ['provider' => $provider->name]),
            ]);
        }
    }
}
