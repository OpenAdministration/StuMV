<?php
namespace App\Ldap;

use App\Ldap\Traits\SearchScopeTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\Relation;
use PhpParser\Node\Expr\AssignOp\Mod;

class User extends \LdapRecord\Models\OpenLDAP\User
{
    use SearchScopeTrait;
    public static function findByUsername(string $username) : ?static
    {
        return self::query()->where('uid', '=', $username)->first();
    }

    public static function findOrFailByUsername(string $username) : static
    {
        return self::findByUsername($username) ?? abort(404);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'uniqueMember');
    }

    public function isSuperAdmin() : bool{
        return SuperUserGroup::group()->members()->exists($this);
    }

    public function adminOf(): HasMany
    {
        $hm = $this->hasMany(Group::class, 'uniqueMember');
        $hm->getQuery()->where('cn', 'admins');
        return $hm;
    }

    public function moderatorOf(): HasMany
    {
        $hm = $this->hasMany(Group::class, 'uniqueMember');
        $hm->getQuery()->where('cn', 'moderators');
        return $hm;
    }

    public function memberOf(): HasMany
    {
        $hm = $this->hasMany(Group::class, 'uniqueMember');
        $hm->getQuery()->where('cn', 'members');
        return $hm;
    }


}
