<?php

namespace App\Ldap;

use App\Ldap\Scopes\AddMemberOfAttributeScope;
use App\Ldap\Traits\SearchScopeTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
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
     * "mail" is multi-valued in inetOrgPerson. The first value is this
     * account's primary address - the immutable one everything outside
     * identity-provider account matching reads (the account dropdown,
     * notifications, password resets, Mailman) via getFirstAttribute('mail')
     * and the "email" column synced from it. Any further values are
     * additional addresses, which exist purely so a login through an external
     * identity provider can find the right account when the provider asserts
     * one of them instead of the primary.
     *
     * LDAP itself gives an attribute's values no defined order, so that
     * "first value" convention only holds because every write here puts the
     * primary back in front - which is why additional addresses must be
     * changed through the two methods below rather than by setting "mail"
     * directly.
     *
     * @return array<int, string>
     */
    public function additionalEmails(): array
    {
        return array_values(array_slice($this->emailValues(), 1));
    }

    public function addAdditionalEmail(string $address): void
    {
        $values = $this->emailValues();

        if (in_array($address, $values, true)) {
            return;
        }

        $this->setAttribute('mail', [...$values, $address]);
    }

    /**
     * Removes an additional address. The primary is never touched - it stays
     * in first position even if it is passed in here.
     */
    public function removeAdditionalEmail(string $address): void
    {
        $values = $this->emailValues();
        $primary = array_shift($values);

        if ($primary === null) {
            return;
        }

        $remaining = array_values(array_filter($values, fn (string $value): bool => $value !== $address));

        $this->setAttribute('mail', [$primary, ...$remaining]);
    }

    /** @return array<int, string> */
    private function emailValues(): array
    {
        return array_values(array_filter((array) $this->getAttribute('mail'), fn ($value): bool => is_string($value) && $value !== ''));
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

    /**
     * pwdLastSuccess is maintained by slapd's core last-bind tracking
     * (olcLastBind/olcLastBindPrecision, OpenLDAP 2.6+ - not the ppolicy
     * overlay), and like pwdAccountLockedTime above is an operational
     * attribute only ever returned via an explicit select().
     */
    public static function lastSuccessfulLoginByUsername(string $username, string $peopleDn): ?Carbon
    {
        $fresh = self::query()
            ->in($peopleDn)
            ->select(['*', 'pwdLastSuccess'])
            ->where('uid', '=', $username)
            ->first();

        $value = $fresh?->getFirstAttribute('pwdLastSuccess');

        return $value ? Date::createFromFormat('YmdHis\Z', $value, 'UTC') : null;
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
