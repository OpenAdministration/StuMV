<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Query\Model\Builder;

/**
 * @method static Builder<static> fromCommunity(string $uid)
 */
class Domain extends Entry
{
    use FromCommunityScopeTrait;
    use SearchScopeTrait;

    /**
     * The object classes of the LDAP model.
     *
     * @var array<int, string>
     */
    public static array $objectClasses = [
        'domain',
        'top',
    ];

    public static function dnRoot(string $uid): string
    {
        return "ou=Domains,ou=$uid,ou=Communities,{base}";
    }

    public function community(): Community
    {
        $dn = DistinguishedName::explode(Dn::of($this));
        $communityDn = implode(',', array_slice($dn, 2));

        // find(), not findOrFail(): only find() bypasses Community's global scopes
        $community = Community::find($communityDn);
        if (! $community instanceof Community) {
            throw new ModelNotFoundException("No community at {$communityDn}.");
        }

        return $community;
    }
}
