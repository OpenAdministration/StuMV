<?php

namespace App\Ldap\Traits;

use App\Ldap\Community;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

trait FromCommunityScopeTrait
{
    /** @param Builder<Model> $query */
    public function scopeFromCommunity(Builder $query, string $uid): void
    {
        $query->setBaseDn("ou=$uid,".Community::$rootDn);
    }
}
