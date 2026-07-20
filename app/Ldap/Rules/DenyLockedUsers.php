<?php

namespace App\Ldap\Rules;

use App\Ldap\User;
use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapRecord;

class DenyLockedUsers implements Rule
{
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return ! User::isLockedByUsername($user->getFirstAttribute('uid'), $user->getParentDn());
    }
}
