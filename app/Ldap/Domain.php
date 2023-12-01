<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use Illuminate\Support\Arr;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\OpenLDAP\Entry;

class Domain extends Entry
{
    use FromCommunityScopeTrait;
    use SearchScopeTrait;

    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'domain',
        'top'
    ];

    public static function dnRoot(string $uid){
        return "ou=Domains,ou=$uid,ou=Communities,{base}";
    }

    public function community() : Community
    {
        $dn = DistinguishedName::explode($this->getDn());
        $communityDn = implode(',', array_slice($dn, 2));
        return Community::find($communityDn);
    }
}
