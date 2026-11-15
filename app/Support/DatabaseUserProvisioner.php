<?php

namespace App\Support;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Makes sure an LDAP account has its `user` row in the given realm.
 *
 * Rows are otherwise only created by the login sync, but role memberships
 * reference user(username, realm) - so an account that exists in LDAP and has
 * never signed in (hand-made, imported, or cloned into another realm by
 * app:split-people-by-realm / app:copy-user) cannot be added to a role.
 */
final class DatabaseUserProvisioner
{
    public static function ensure(Community $realm, LdapUser $ldapUser): User
    {
        $realmUid = $realm->getShortCode();
        $username = (string) $ldapUser->getFirstAttribute('uid');
        $guid = $ldapUser->getConvertedGuid();

        // A row signed in before realms were stored has no realm yet but already
        // carries this entry's GUID - that is the same account.
        $row = User::where('username', $username)->where('realm', $realmUid)->first()
            ?? User::where('uid', $guid)->whereNull('realm')->first()
            ?? self::newRow($username, $ldapUser);

        if ($row->uid !== $guid) {
            // uid is unique; a row of another realm may still hold this GUID from
            // before the split - it gets its own entry's GUID when its turn comes.
            User::where('uid', $guid)->when($row->exists, fn ($query) => $query->whereKeyNot($row->getKey()))->update(['uid' => null]);
        }

        $row->forceFill(['uid' => $guid, 'realm' => $realmUid])->save();

        return $row;
    }

    private static function newRow(string $username, LdapUser $ldapUser): User
    {
        $email = (string) $ldapUser->getFirstAttribute('mail');

        return (new User)->forceFill([
            'username' => $username,
            'full_name' => (string) $ldapUser->getFirstAttribute('cn'),
            'email' => $email,
            // A clone of an account whose address was already verified keeps that.
            'email_verified_at' => User::where('username', $username)->where('email', $email)
                ->whereNotNull('email_verified_at')->value('email_verified_at'),
            // LDAP stays the source of truth for authentication (sync_passwords is off).
            'password' => password_hash(Str::random(40), PASSWORD_ARGON2ID),
        ]);
    }
}
