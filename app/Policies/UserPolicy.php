<?php

namespace App\Policies;


use App\Models\User;

class UserPolicy
{
    public function superadmin() : bool {
        return auth()->user()?->ldap()->isSuperAdmin();
    }
}
