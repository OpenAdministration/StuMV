<?php

namespace App\Policies;


use App\Ldap\Community;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user, Community $community)
    {
        return $user->can('superadmin', User::class)
            || $user->can('admin', $community);
    }

    public function create(User $user, Community $community)
    {
        return $user->can('superadmin', User::class)
            || $user->can('admin', $community);
    }

    public function edit(User $user, Community $community)
    {
        return $user->can('superadmin', User::class)
            || $user->can('admin', $community);
    }

    public function delete(User $user, Community $community)
    {
        return $user->can('superadmin', User::class)
            || $user->can('admin', $community);
    }
}
