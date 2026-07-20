<?php

namespace App\Ldap;

use App\Ldap\Scopes\AddMemberOfAttributeScope;
use App\Ldap\Traits\SearchScopeTrait;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\Relations\HasMany;

class User extends \LdapRecord\Models\OpenLDAP\User
{
    use SearchScopeTrait;

    /**
     * The "booting" method of the model.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new AddMemberOfAttributeScope);
    }

    public static function findByUsername(string $username): ?static
    {
        return self::query()->where('uid', '=', $username)->first();
    }

    public static function findOrFailByUsername(string $username): static
    {
        return self::findByUsername($username) ?? abort(404);
    }

    public static function findByEmail(string $email): ?static
    {
        return self::query()->where('mail', '=', $email)->first();
    }

    /**
     * pwdAccountLockedTime is an operational attribute: the LDAP server only
     * returns it when explicitly named in a select (never via a plain "*"
     * fetch, and not even via a base-scoped find() by DN), so it must always
     * be checked through a fresh, explicit where()-based query like this one.
     *
     * $peopleDn scopes the search to the one People branch this specific
     * account actually lives under (its own getParentDn()) - required now
     * that the same uid can independently exist in more than one realm.
     */
    public static function isLockedByUsername(string $username, string $peopleDn): bool
    {
        $fresh = self::query()
            ->in($peopleDn)
            ->select(['*', 'pwdAccountLockedTime'])
            ->where('uid', '=', $username)
            ->first();

        return (bool) $fresh?->hasAttribute('pwdAccountLockedTime');
    }

    #[\Override]
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'uniqueMember');
    }

    public function isSuperAdmin(): bool
    {
        return Community::membershipFor($this) === Community::ADMIN_REALM_UID;
    }

    public function adminOf(): HasMany
    {
        $hm = $this->hasMany(Group::class, 'uniqueMember');
        $hm->getQuery()->where('cn', '=', 'admins');

        return $hm;
    }

    public function moderatorOf(): HasMany
    {
        $hm = $this->hasMany(Group::class, 'uniqueMember');
        $hm->getQuery()->where('cn', '=', 'moderators');

        return $hm;
    }
}
