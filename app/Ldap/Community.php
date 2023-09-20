<?php

namespace App\Ldap;

use App\Ldap\Relations\HasOneChild;
use App\Ldap\Traits\HasRelationships;
use App\Ldap\Traits\SearchScopeTrait;
use LdapRecord\Laravel\ImportableFromLdap;
use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Models\Relations\Relation;
use LdapRecord\Query\Builder;

/***
 * @property $ou
 * @property $description
 */
class Community extends OrganizationalUnit implements LdapImportable
{
    use ImportableFromLdap;
    use SearchScopeTrait;
    use HasRelationships;

    public static string $rootDn = 'ou=Communities,{base}';

    public static function findByUid(string $uid): self|null
    {
        return self::query()
            ->whereEquals('ou', $uid)
            ->first()
            ;
    }

    public static function findOrFailByUid(string $uid) : self {
        return self::findByUid($uid) ?? abort(404);
    }

    public function getShortCode(){
        return $this->ou[0];
    }

    public function getLongName(){
        return $this->description[0] ?? '';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('limitResults', static function (Builder $builder){
            $builder->in(self::$rootDn)
                ->where('ou', '!=', 'Communities');
        });
    }

    public function membersGroup() : HasOneChild {
        return $this->hasOneChild(Group::class, 'cn=members');
    }

    public function moderatorsGroup() : HasOneChild {
        return $this->hasOneChild(Group::class, 'cn=moderators');
    }

    public function adminsGroup() : HasOneChild {
        return $this->hasOneChild(Group::class, 'cn=admins');
    }

}
