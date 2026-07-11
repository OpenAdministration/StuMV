<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Models\Relations\HasManyIn;

class SuperUserGroup extends Group
{
    public static function group() : self
    {
        return self::query()->findOrFail('cn=super-admins,{base}');
    }

    public function members(): HasManyIn
    {
        return $this->hasManyIn([User::class], 'uniquemember')->using($this, 'uniquemember');
    }
}
