<?php

namespace App\Auth\Passwords;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Carbon;

/**
 * The same email address can legitimately belong to independent accounts in
 * different realms (see the `user_username_realm_unique` migration), so every
 * query here must also be scoped by realm - otherwise requesting or
 * consuming a reset link for one realm's account could invalidate, or worse,
 * reset the password of a different realm's account that happens to share
 * the same email. $user is always the specific, realm-scoped App\Models\User
 * row the ldaprecord provider resolved (already filtered via
 * ScopedToRealmPeople), so $user->realm is always the right value to key on.
 */
class RealmScopedTokenRepository extends DatabaseTokenRepository
{
    #[\Override]
    public function create(CanResetPasswordContract $user)
    {
        $email = $user->getEmailForPasswordReset();

        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert([
            'email' => $email,
            'realm' => $user->realm,
            'token' => $this->hasher->make($token),
            'created_at' => new Carbon,
        ]);

        return $token;
    }

    #[\Override]
    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->where('realm', $user->realm)
            ->delete();
    }

    #[\Override]
    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->where('realm', $user->realm)
            ->first();

        return $record
            && ! $this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    #[\Override]
    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->where('realm', $user->realm)
            ->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }
}
