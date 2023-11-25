<?php

namespace App\Policies;

use App\Ldap\User;

class CommunityPolicy
{
    public function create(\App\Models\User $user){
        return $user->ldap()->isSuperAdmin();
    }

    public function picked() : bool
    {
        return session()->exists('realm_uid');
    }
}
