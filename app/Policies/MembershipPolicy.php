<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleUserRelation;
use App\Models\User;

class MembershipPolicy
{
    public function create(User $user, Community $community) : bool {
        return // add committee mods
            $user->can('moderator', $community);
    }

    public function edit(User $user, RoleUserRelation $membership, Community $community) : bool {
        return $user->can('moderator', $community);
    }

    public function delete(User $user, RoleUserRelation $membership, Community $community) : bool {
        return $user->can('moderator', $community);
    }

    public function view(User $user, RoleUserRelation $membership, Community $community) : bool {
        return $user->can('member', $community)
            || $user->can('superadmin');
    }

}
