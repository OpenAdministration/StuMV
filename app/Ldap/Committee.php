<?php

namespace App\Ldap;

use App\Ldap\Traits\FromCommunityScopeTrait;
use App\Ldap\Traits\SearchScopeTrait;
use Illuminate\Support\Arr;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\DistinguishedNameBuilder;
use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;

class Committee extends OrganizationalUnit
{
    use SearchScopeTrait;


    public static function dnFrom(string $uid, string $ou, array $parent_ous = null, string $parentDn = ''){
        // if dn is given short circuit the method
        if(!empty($parentDn)){
            return "ou=$ou," . $parentDn;
        }
        // standardize input
        if(is_null($parent_ous)){
            $parent_ous = [];
        }
        $parents = implode(',ou=', $parent_ous);
        return "ou=$ou," . $parents . self::dnRoot($uid);
    }

    public static function scopeFromCommunity(Builder $query, string $uid): Builder
    {
        return $query->in(self::dnRoot($uid))
            ->whereNotEquals('ou', 'Committees');
    }

    public static function dnRoot(string $uid){
        return "ou=Committees,ou=$uid,ou=Communities,{base}";
    }

    public function setDnFrom(string $uid, string|array $ous): static
    {
        $dn = self::dnFrom($uid, $ous);
        return parent::setDn($dn);
    }

    public function parentCommittee() : ?Committee
    {
        $dn = DistinguishedName::make($this->getDn());
        $parentDn = $dn->parent();
        if(!str_contains($parentDn, ',ou=Committees,')){
            return null;
        }
        return self::findOrFail($parentDn);
    }

    public function getFullName() : string{
        return $this->getFirstAttribute('description');
    }

    public function getShortName() : string{
        return $this->getFirstAttribute('ou');
    }

    /**
     * @return array returns all ou's inside the ou=Committees path starting with the uppermost Entry
     */
    public function committeePath(): array {
        $dn = new DistinguishedNameBuilder($this->getDn());
        $ous = $dn->pop(5); // only real parents are left
        $ous =$ous->components();
        return array_reverse(Arr::map($ous, function ($entry){
            return $entry[1];
        }));
    }

    /**
     * @return array returns all ou's inside the ou=Committees path starting with the uppermost Entry but without itself
     */
    public function parentCommitteePath() : array {
        return array_slice($this->committeePath(), -1);
    }

    /**
     * @return Builder returns a querry wich
     */
    public function roles() : Builder {
        return Role::query()
            ->list()
            ->setBaseDn($this->getDn())
        ;
    }

    public static function findByName(string $uid, string $name) : ?self {
        return self::fromCommunity($uid)->where('ou', $name)->first();
    }

    public static function findByNameOrFail(string|Community $community, string $name) : self
    {
        if($community instanceof Community){
            $uid = $community->getFirstAttribute('ou');
        }else{
            $uid = $community;
        }
        return self::fromCommunity($uid)->where('ou', $name)->first() ?? abort(404);
    }

}
