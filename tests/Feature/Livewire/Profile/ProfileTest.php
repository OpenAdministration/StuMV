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
        ->set('phone', '+4930123456')
        ->set('city', 'Berlin')
        ->call('save')
        ->assertHasNoErrors();

    $ldap = LdapUser::findByUsername($user->username);
    expect($ldap->getFirstAttribute('givenName'))->toBe('Neu')
        ->and($ldap->getFirstAttribute('sn'))->toBe('Name')
        ->and($ldap->getFirstAttribute('cn'))->toBe('Neu Name')
        ->and($ldap->getFirstAttribute('telephoneNumber'))->toBe('+4930123456')
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
