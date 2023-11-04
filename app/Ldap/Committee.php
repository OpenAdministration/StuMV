<?php

namespace App\Ldap;

use App\Ldap\Traits\SearchScopeTrait;
use Illuminate\Support\Str;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Query\Model\Builder;

class Committee extends OrganizationalUnit
{
    use SearchScopeTrait;

    public static function dnFrom(string $uid, string $ou, array|string $parent_ou = null){
        if(empty($parent_ou)){
            $parent_ou = self::dnRoot($uid);
        }
        if(is_array($parent_ou)){
            $parent_ou = implode(',ou=', $parent_ou);
        }
        return "ou=$ou," . $parent_ou;
    }

    public static function scopeFromCommunity(Builder $query, string $realm_uid): Builder
    {
        return $query->in(self::dnRoot($realm_uid))
            ->whereNotEquals('ou', 'Committees');
    }

    public static function dnRoot(string $uid){
        return "ou=Committees,ou=$uid,ou=Communities," . config('ldap.connections.default.base_dn');
    }

    public function setDnFrom(string $uid, string|array $ous): static
    {
        $dn = self::dnFrom($uid, $ous);
        return parent::setDn($dn);
    }

    public function parentCommittee() : ?Committee
    {
        $dn = Str::of($this->getDn());
        $parentDn = $dn->explode(',')->slice(1)->implode(',');
        if(!str_contains($parentDn, ',ou=Committees,')){
            return null;
        }
        return self::findOrFail($parentDn);
    }

}
