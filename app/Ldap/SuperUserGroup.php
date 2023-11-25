<?php

namespace App\Ldap;

use LdapRecord\Models\Model;

class SuperUserGroup extends \LdapRecord\Models\OpenLDAP\Group
{
    public static function group() : self
    {
        return self::query()->findOrFail('cn=super-admins,{base}');
    }
}
