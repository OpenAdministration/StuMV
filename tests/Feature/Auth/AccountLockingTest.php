<?php

use App\Ldap\User as LdapUser;

test('a locked user is logged out on their very next request', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $uid = $community->getShortCode();

    // Sanity check: the session works before the account is locked.
    $this->get("/$uid/profile/".$user->username)->assertStatus(200);

    $ldap = LdapUser::findByUsername($user->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    $this->get("/$uid/profile/".$user->username)->assertRedirect(route('realm.login', ['realm' => $uid]));

    $this->assertGuest();
});

test('an active user is not affected by the lock check', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $uid = $community->getShortCode();

    $this->get("/$uid/profile/".$user->username)->assertStatus(200);

    $this->assertAuthenticated();
});
