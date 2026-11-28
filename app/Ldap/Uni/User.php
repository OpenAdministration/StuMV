<?php

namespace App\Ldap\Uni;

use LdapRecord\Models\Model;

class User extends Model
{
    protected ?string $connection = 'uni';

    /**
     * The object classes of the LDAP model.
     *
     * @var array<int, string>
     */
    public static array $objectClasses = [];
}
