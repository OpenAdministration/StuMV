<?php

namespace App\Policies;

use App\Ldap\Community;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class CommunityPolicy
{
    public function create(User $user)
    {
        return $user->can('superadmin', User::class);
    }

    public function picked(): bool
    {
        // The nullsafe operator short-circuits the whole expression to null
        // (not false) when there's no current route at all - Route::current()
        // is null - which this method's strict bool return type rejects.
        return Route::current()?->hasParameter('realm') ?? false;
        // return session()->exists('realm_uid');
    }

    public function enter(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class)
            || $this->member($user, $community);
    }

    public function edit(User $user, Community $community): bool
    {
        return $this->admin($user, $community);
    }

    public function delete(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class);
    }

    public function member(User $user, Community $community): bool
    {
        return str_ends_with((string) $user->ldap()->getDn(), ','.$community->peopleDn());
    }

    public function add_member(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class);
    }

    public function remove_member(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class);
    }

    public function moderator(User $user, Community $community): bool
    {
        // Admin-realm members get moderator/admin/superadmin rights in every
        // realm, not just their own - checked directly here (not just via
        // the superadmin `||` some callers below already add) so every
        // consumer of the raw "moderator" ability picks it up too, including
        // ones that never compose it with an explicit superadmin check.
        //
        // The admin realm itself has no moderators group of its own (see
        // Community::generateSkeleton()) - only superadmin rights apply
        // within it, so this must short-circuit before touching
        // moderatorsGroup(), which would otherwise be null there.
        if ($community->isAdminRealm()) {
            return $user->can('superadmin', User::class);
        }

        return $user->can('superadmin', User::class)
            || $community->moderatorsGroup()->members()->exists($user->ldap());
    }

    public function tools(User $user, Community $community): bool
    {
        return $this->admin($user, $community)
            || $this->moderator($user, $community);
    }

    public function add_moderator(User $user, Community $community): bool
    {
        return $this->admin($user, $community)
            || $this->moderator($user, $community);
    }

    public function remove_moderator(User $user, Community $community): bool
    {
        return $this->admin($user, $community)
            || $this->moderator($user, $community);
    }

    public function admin(User $user, Community $community): bool
    {
        // See moderator() above - admin-realm members get admin rights
        // everywhere, checked directly here for the same reason. Same
        // short-circuit for the admin realm itself, which has no admins
        // group of its own.
        if ($community->isAdminRealm()) {
            return $user->can('superadmin', User::class);
        }

        return $user->can('superadmin', User::class)
            || $community->adminsGroup()->members()->exists($user->ldap());
    }

    public function add_admin(User $user, Community $community): bool
    {
        return $this->admin($user, $community);
    }

    public function remove_admin(User $user, Community $community): bool
    {
        return $this->admin($user, $community);
    }
}
