<?php

namespace App\Policies;

use App\Ldap\Committee;
use App\Ldap\Role;
use App\Models\RoleUserRelation;
use App\Models\User;

class RolePolicy
{
    public function create(User $user, Committee $committee) : bool {
        return // add committee mods
            $user->can('moderator', $committee);
    }

    public function edit(User $user, Role $membership, Committee $committee) : bool {
        return $user->can('moderator', $committee);
    }

    public function delete(User $user, Role $membership, Committee $committee) : bool {
        return $user->can('moderator', $committee);
    }

    public function view(User $user, Role $membership, Committee $committee) : bool {
        return $user->can('member', $committee)
            || $user->can('superadmin');
    }
}
