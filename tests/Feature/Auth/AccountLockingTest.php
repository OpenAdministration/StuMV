<?php

use App\Ldap\User as LdapUser;

test('a locked user is logged out on their very next request', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    // Sanity check: the session works before the account is locked.
    $this->get('/profile/'.$user->username)->assertStatus(200);

    $ldap = LdapUser::findByUsername($user->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    $this->get('/profile/'.$user->username)->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active user is not affected by the lock check', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    $this->get('/profile/'.$user->username)->assertStatus(200);

    $this->assertAuthenticated();
});
