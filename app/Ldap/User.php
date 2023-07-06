<?php
namespace App\Ldap;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\Model;

class User extends Model implements Authenticatable
{
    use CanAuthenticate;
    use HasPassword;

    protected string $guidKey = 'uid';

    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetOrgPerson',
    ];

}
