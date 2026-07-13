<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\User;

class CommitteePolicy
{
    public function moderator(User $user, Committee $committee, Community $community)
    {
        return $user->can('moderator', $community) || $committee->hasModerator($user);
    }

    /**
     * Gates entry points that don't yet have a specific committee to check
     * against (e.g. picking a parent for a brand new committee) - true if
     * the user could possibly create/manage something in this community,
     * either as a community moderator or as a moderator of any committee in
     * it. Callers still need to re-check the specific target once known
     * (e.g. via the moderator ability above).
     */
    public function create(User $user, Community $community): bool
    {
        return $user->can('moderator', $community) || $community->hasCommitteeModeratorSomewhere($user);
    }

    public function edit(User $user, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', [$committee, $community]);
    }

    public function delete(User $user, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', [$committee, $community]);
    }

    public function viewAny(User $user, Community $community): bool
    {
        return $user->can('member', $community)
            || $user->can('superadmin');
    }
}
