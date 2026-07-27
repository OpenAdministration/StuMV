<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleMembership;
use App\Models\User;

class MembershipPolicy
{
    public function create(User $user, Committee $committee, Community $community): bool
    {
        return // add committee mods
            $user->can('moderator', [$committee, $community]);
    }

    public function edit(User $user, RoleMembership $membership, Committee $committee, Community $community): bool
    {
        return $this->belongsToCommittee($membership, $committee)
            && $user->can('moderator', [$committee, $community]);
    }

    public function terminate(User $user, RoleMembership $membership, Committee $committee, Community $community): bool
    {
        return $this->belongsToCommittee($membership, $committee)
            && $user->can('moderator', [$committee, $community]);
    }

    public function delete(User $user, RoleMembership $membership, Committee $committee, Community $community): bool
    {
        return $this->belongsToCommittee($membership, $committee)
            && $user->can('moderator', [$committee, $community]);
    }

    public function view(User $user, RoleMembership $membership, Committee $committee, Community $community): bool
    {
        return $this->belongsToCommittee($membership, $committee)
            && ($user->can('member', [$committee, $community]) || $user->can('superadmin'));
    }

    /**
     * $committee/$community are derived from the current route, so without
     * this check any of the above would authorize acting on a $membership
     * belonging to a completely different committee/realm, as long as the
     * caller moderates *some* committee.
     */
    protected function belongsToCommittee(RoleMembership $membership, Committee $committee): bool
    {
        return $membership->committee_dn === $committee->getDn();
    }
}
