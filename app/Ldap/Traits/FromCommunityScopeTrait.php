<?php

namespace App\Ldap\Traits;

use App\Ldap\Community;
use LdapRecord\Query\Builder;

trait FromCommunityScopeTrait {
    public function scopeFromCommunity(Builder $query, string $uid): void
    {
        $query->setBaseDn("ou=$uid," . \App\Ldap\Community::$rootDn);
    }
}
