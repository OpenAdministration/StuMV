<?php

namespace App\Support;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use LdapRecord\LdapRecordException;

class LdapAccountRegistrar
{
    /**
     * Creates the LDAP entry for a brand-new account under $realm's People
     * branch, then syncs it into the database via the same path a real login
     * would use, and stamps its realm. Shared by every self-service-style
     * registration flow (App\Livewire\RegisterUser, App\Livewire\AcceptInvitation)
     * so this security-sensitive sequence exists in exactly one place.
     *
     * @throws LdapRecordException
     */
    public function register(
        Community $realm,
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        string $password,
    ): User {
        $user = new LdapUser([
            'uid' => $username,
            'cn' => $firstName.' '.$lastName,
            'sn' => $lastName,
            'givenName' => $firstName,
            'mail' => $email,
            'userPassword' => '{ARGON2}'.password_hash($password, PASSWORD_ARGON2ID),
            // usually ldap SHOULD hash it itself - did not work
        ]);
        $user->setDn("uid=$username,".$realm->peopleDn());

        $user->save();
        // Membership is the location itself now - no group to attach to.

        // entryUUID is server-assigned and not part of the in-memory model
        // right after an insert - refresh so getConvertedGuid() below
        // actually returns it.
        $user->refresh();

        // Credentials must be keyed for the LDAP guard (see LoginRequest); a
        // positional array does not validate. Auth::validate (not attempt)
        // syncs the LDAP user into the database without logging the freshly
        // registered user in. Callers here run through Livewire's own update
        // mechanism, not a plain request bound directly to a {realm} route,
        // so the current request has no bound {realm} route parameter for
        // ScopedToRealmPeople to read - it has to be told explicitly here.
        resolve(RealmContext::class)->set($realm);
        Auth::validate(['uid' => $username, 'password' => $password]);

        // Resolve by the fresh LDAP entry's own GUID, not by username - a
        // username search could now match a different realm's existing
        // account sharing the same username.
        $eloquentUser = User::where('uid', $user->getConvertedGuid())->first();
        $eloquentUser->update([
            'realm' => $realm->getFirstAttribute('ou'),
        ]);

        return $eloquentUser;
    }
}
