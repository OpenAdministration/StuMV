<?php

namespace App\Console\Commands\Concerns;

/**
 * Shared by SplitPeopleByRealm and CopyUserToRealm - both clone an existing
 * LDAP entry's attributes onto a brand-new DN.
 */
trait StripsLdapOperationalAttributes
{
    /**
     * The attributes an LDAP search never returns unless explicitly selected,
     * stripped defensively before cloning an entry so they're never sent back
     * to the server as if they were ordinary user attributes. memberof is a
     * dynlist-computed virtual attribute (see AddMemberOfAttributeScope,
     * applied to every App\Ldap\User query) - present whenever the source
     * entry belongs to any group, and rejected outright by the server if
     * ever included in an add.
     */
    private const array OPERATIONAL_ATTRIBUTES = [
        'entryuuid', 'entrycsn', 'creatorsname', 'createtimestamp',
        'modifiersname', 'modifytimestamp', 'structuralobjectclass',
        'subschemasubentry', 'hassubordinates', 'pwdchangedtime',
        'pwdaccountlockedtime', 'pwdfailuretime', 'pwdhistory', 'memberof',
    ];

    private function withoutOperationalAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::OPERATIONAL_ATTRIBUTES));
    }
}
