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
            || $this->member($user, $community)
            || $this->moderator($user, $community)
            || $this->admin($user, $community);
    }

    public function member(User $user, Community $community): bool
    {
        $mg = $community->membersGroup();
        return $user->ldap()->memberOf()->exists($mg);
    }

    public function moderator(User $user, Community $community): bool
    {
        $mg = $community->moderatorsGroup();
        return $user->ldap()->memberOf()->exists($mg);
    }

    public function admin(User $user, Community $community): bool
    {
        $ag = $community->adminsGroup();
        return $user->ldap()->memberOf()->exists($ag);
    }
}
