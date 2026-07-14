<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function superadmin(): bool
    {
        return auth()->user()?->ldap()->isSuperAdmin();
    }

    /**
     * Whether the user may view/manage the profile identified by $username
     * (profile details, password, memberships, …): their own, or any if they
     * are a super admin.
     */
    public function manageProfile(User $user, string $username): bool
    {
        return $user->username === $username || $user->ldap()->isSuperAdmin();
    }
}
