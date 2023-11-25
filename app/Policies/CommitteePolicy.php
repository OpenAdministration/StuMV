<?php

namespace App\Policies;


use App\Ldap\Community;
use App\Models\User;

class CommitteePolicy
{
    public function create(User $user, Community $community) : bool {
        return $user->can('admin', $community)
            || $user->can('moderator', $community);
    }

}
