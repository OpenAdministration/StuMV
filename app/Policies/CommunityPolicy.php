<?php

namespace App\Policies;


use App\Ldap\Community;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class CommunityPolicy
{

    public function create(User $user){
        return $user->can('superadmin', User::class);
    }


    public function picked() : bool
    {
        return Route::current()?->hasParameter('uid');
        //return session()->exists('realm_uid');
    }

    public function enter(User $user, Community $community) : bool{
        return $user->can('superadmin', User::class)
            || $this->member($user, $community);
    }

    public function edit(User $user, Community $community) : bool{
        return $user->can('superadmin', User::class)
            || $this->admin($user, $community);
    }

    public function delete(User $user, Community $community) : bool{
        return $user->can('superadmin', User::class)
            || $this->admin($user, $community);
    }

    public function member(User $user, Community $community): bool
    {
        return $community->membersGroup()->members()->exists($user->ldap());
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
        return $community->moderatorsGroup()->members()->exists($user->ldap());
    }

    public function add_moderator(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class)
            || $this->admin($user, $community)
            || $this->moderator($user, $community);
    }

    public function admin(User $user, Community $community): bool
    {
        return $community->adminsGroup()->members()->exists($user->ldap());
    }

    public function add_admin(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class)
            || $this->admin($user, $community);
    }

    public function remove_admin(User $user, Community $community): bool
    {
        return $user->can('superadmin', User::class)
            || $this->admin($user, $community);
    }

}
