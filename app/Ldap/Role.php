<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use App\Models\RoleMembership;
use LdapRecord\Models\OpenLDAP\Group;

class Role extends Group
{
    use FromCommunityScopeTrait;
    use SearchScopeTrait;

    public function dbMemberships()
    {
        $cn = explode('=', (string) $this->getRdn(), 2)[1];
        $dn = $this->getParentDn();

        return RoleMembership::query()
            // ->join('user', 'user.uid', '=', 'role_user_relation.username', 'left')
            // ->select('role_user_relation.*', 'user.full_name')
            ->where('role_cn', $cn)
            ->where('committee_dn', $dn);
    }

    public function committee(): ?Committee
    {
        return Committee::find($this->getParentDn());
    }
}
