<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a user can edit their own profile', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['username' => $user->username])
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

test('first and last name are required', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['username' => $user->username])
        ->set('givenName', '')
        ->call('save')
        ->assertHasErrors('givenName');
});

test('a user cannot open someone else profile', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Profile::class, ['username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('a super admin can open any profile', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    Livewire::test(Profile::class, ['username' => $target->username])
        ->assertStatus(200)
        ->assertSet('uid', $target->username);
});
