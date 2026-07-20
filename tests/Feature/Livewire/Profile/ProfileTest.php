<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Profile\Profile;
use App\Models\User as DbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a user can edit their own profile', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('givenName', 'Neu')
        ->set('sn', 'Name')
        ->set('phone', '+49 30 1234')
        ->set('city', 'Berlin')
        ->call('save')
        ->assertHasNoErrors();

    $ldap = LdapUser::findByUsername($user->username);
    expect($ldap->getFirstAttribute('givenName'))->toBe('Neu')
        ->and($ldap->getFirstAttribute('sn'))->toBe('Name')
        ->and($ldap->getFirstAttribute('cn'))->toBe('Neu Name')
        ->and($ldap->getFirstAttribute('telephoneNumber'))->toBe('+49 30 1234')
        ->and($ldap->getFirstAttribute('l'))->toBe('Berlin');
});

test('saving the profile updates the full name in the database too', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('givenName', 'Neu')
        ->set('sn', 'Name')
        ->call('save')
        ->assertHasNoErrors();

    expect(DbUser::where('username', $user->username)->value('full_name'))->toBe('Neu Name');
});

test('first and last name are required', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('givenName', '')
        ->call('save')
        ->assertHasErrors('givenName');
});

test('a user cannot open someone else profile', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('a super admin can open any profile', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $target->username])
        ->assertStatus(200)
        ->assertSet('uid', $target->username);
});

test('a realm admin can open another users profile in their own realm', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $target->username])
        ->assertStatus(200)
        ->assertSet('uid', $target->username);
});

test('a realm admin cannot open a profile in a different realm', function (): void {
    $adminRealm = newCommunity();
    $otherRealm = newCommunity();
    $target = TestLdap::member($otherRealm);
    actingAsAdmin($adminRealm);

    Livewire::test(Profile::class, ['realm' => $otherRealm, 'username' => $target->username])
        ->assertForbidden();
});

test('a super admin sees the account-active switch and can disable a user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $target->username])
        ->assertSee(__('profile.user_is_active'))
        ->set('userIsActive', false)
        ->call('save')
        ->assertHasNoErrors();

    // pwdAccountLockedTime is an operational attribute, so it must be
    // explicitly named in the select or the server won't return it.
    $ldap = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($ldap->getFirstAttribute('pwdAccountLockedTime'))->toBe('00000101000000Z');
});

test('a disabled user shows as inactive when the profile loads', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    $ldap = LdapUser::findByUsername($target->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $target->username])
        ->assertSet('userIsActive', false);
});

test('a super admin can re-enable a previously disabled user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    $ldap = LdapUser::findByUsername($target->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $target->username])
        ->assertSet('userIsActive', false)
        ->set('userIsActive', true)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($fresh->hasAttribute('pwdAccountLockedTime'))->toBeFalse();
});

test('a regular user does not see the account-active switch on their own profile', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->assertDontSee(__('profile.user_is_active'));
});

test('a non-superadmin cannot lock their own account by tampering with the field directly', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('userIsActive', false)
        ->call('save')
        ->assertForbidden();

    $ldap = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $user->username)
        ->first();
    expect($ldap->hasAttribute('pwdAccountLockedTime'))->toBeFalse();
});

test('a non-superadmin cannot re-enable a locked account by tampering with the field directly', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    $ldap = LdapUser::findByUsername($user->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('userIsActive', true)
        ->call('save')
        ->assertForbidden();

    $fresh = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $user->username)
        ->first();
    expect($fresh->hasAttribute('pwdAccountLockedTime'))->toBeTrue();
});
