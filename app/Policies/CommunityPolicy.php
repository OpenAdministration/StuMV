<?php

namespace App\Policies;


use App\Ldap\Community;
use App\Models\User;

class CommunityPolicy
{
    /**
     * Short-circuit all other methods and allows if super admin
     * @param User $user
     * @param string $ability
     * @return true|null
     */
    public function before(User $user, string $ability): true|null{
        if($user->ldap()->isSuperAdmin()){
            return true;
        }
        return null;
    }
    public function create(User $user){
        return $user->ldap()->isSuperAdmin();
    }

    public function picked() : bool
    {
        return session()->exists('realm_uid');
    }

    public function enter(User $user, Community $community) : bool{
        return $this->member($user, $community)
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
