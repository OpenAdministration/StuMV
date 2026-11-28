<?php

namespace App\Ldap;

use LdapRecord\Models\Model;
use LogicException;

/** DNs of entries that exist in LDAP; LdapRecord types them nullable for unsaved models. */
final class Dn
{
    public static function of(Model $entry): string
    {
        return $entry->getDn() ?? throw new LogicException($entry::class.' has no DN; it was never saved or loaded.');
    }

    public static function parentOf(Model $entry): string
    {
        return $entry->getParentDn() ?? throw new LogicException($entry::class.' has no parent DN.');
    }
}
