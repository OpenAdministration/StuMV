<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\User;

class RolePolicy
{
    public function create(User $user, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', [$committee, $community]);
    }

    public function edit(User $user, Role $role, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', [$committee, $community]);
    }

    public function delete(User $user, Role $role, Committee $committee, Community $community): bool
    {
        return $user->can('moderator', [$committee, $community]);
    }

    public function view(User $user, Role $role, Committee $committee, Community $community): bool
    {
        return $user->can('member', $community)
            || $user->can('superadmin');
    }
}
