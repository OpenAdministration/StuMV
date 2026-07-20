<?php

namespace App\Policies;

use App\Ldap\Community;
use App\Models\User;

class UserPolicy
{
    public function superadmin(): bool
    {
        return auth()->user()?->ldap()->isSuperAdmin();
    }

    /**
     * Whether the user may view/manage the profile identified by
     * ($realm, $username) - profile details, password, memberships, … -
     * their own, any if they are a super admin, or any within a realm they
     * administer. Matching on realm too (not just username) matters now
     * that the same username can belong to independent accounts in
     * different realms - an admin of realm A must not be able to manage a
     * same-named account in realm B.
     */
    public function manageProfile(User $user, Community $realm, string $username): bool
    {
        return ($user->username === $username && $user->realm === $realm->getShortCode())
            || $user->ldap()->isSuperAdmin()
            || $user->can('admin', $realm);
    }
}
