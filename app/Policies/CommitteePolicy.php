<?php

namespace App\Policies;


use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\User;

class CommitteePolicy
{
    public function moderator(User $user, Committee $committee, Community $community,)
    {
        return $user->can('moderator', $community);
    }

    public function create(User $user, Community $community) : bool {
        return // add committee mods
            $user->can('moderator', $community);
    }

    public function edit(User $user, Committee $committee, Community $community) : bool {
        return $user->can('moderator', $community);
    }

    public function delete(User $user, Committee $committee, Community $community) : bool {
        return $user->can('moderator', $community);
    }

    public function viewAny(User $user, Community $community) : bool {
        return $user->can('member', $community)
            || $user->can('superadmin');
    }

}
