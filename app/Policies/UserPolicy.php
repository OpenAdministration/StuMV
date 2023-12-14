<?php

namespace App\Policies;


class UserPolicy
{
    public function superadmin() : bool {
        return auth()->user()?->ldap()->isSuperAdmin();
    }
}
