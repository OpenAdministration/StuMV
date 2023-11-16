<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use LdapRecord\Query\Builder;

class Role extends \LdapRecord\Models\OpenLDAP\Group
{
    use SearchScopeTrait;
    use FromCommunityScopeTrait;

}
