<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\User;

class CommitteePolicy
{
    /**
     * Whether $user moderates this committee specifically (directly, or via
     * an ancestor committee) - used only by RolePolicy/MembershipPolicy for
     * role and role-membership actions. Committee moderators do NOT get this
     * for committee create/edit/delete themselves - those stay
     * community-moderator-only, see edit()/delete()/create() below.
     */
    public function moderator(User $user, Committee $committee, Community $community)
    {
        return $user->can('moderator', $community) || $committee->hasModerator($user);
    }

    public function create(User $user, Community $community): bool
    {
        return $user->can('moderator', $community);
    }

    public function edit(User $user, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', $community);
    }

    public function delete(User $user, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', $community);
    }

    public function viewAny(User $user, Community $community): bool
    {
        return $user->can('member', $community)
            || $user->can('superadmin');
    }
}
