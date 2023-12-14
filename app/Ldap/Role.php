<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use App\Models\RoleMembership;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Models\Relations\HasManyIn;
use LdapRecord\Query\Builder;

class Role extends \LdapRecord\Models\OpenLDAP\Group
{
    use SearchScopeTrait;
    use FromCommunityScopeTrait;


    public function dbMemberships(){
        $cn = explode("=", $this->getRdn(), 2)[1];
        $dn = $this->getParentDn();
        return RoleMembership::query()
            ->where('role_cn', $cn)
            ->where('committee_dn', $dn);
    }

}
