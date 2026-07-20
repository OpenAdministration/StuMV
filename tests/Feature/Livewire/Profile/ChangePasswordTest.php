<?php

use App\Livewire\Profile\ChangePassword;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a user can change their own password and log in with it', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $newPassword = 'Znew1!'.bin2hex(random_bytes(6));

    Livewire::test(ChangePassword::class, ['realm' => $community, 'username' => $user->username])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    // Prove the directory really accepts the new password on bind.
    auth()->logout();
    $this->post('/'.$community->getShortCode().'/login', ['uid' => $user->username, 'password' => $newPassword])
        ->assertSessionHasNoErrors()
        ->assertRedirect(RouteServiceProvider::home($community->getShortCode()));
    $this->assertAuthenticated();
});

test('a super admin can change another user password', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();
    $newPassword = 'Zsu1!'.bin2hex(random_bytes(6));

    Livewire::test(ChangePassword::class, ['realm' => $community, 'username' => $target->username])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    auth()->logout();
    $this->post('/'.$community->getShortCode().'/login', ['uid' => $target->username, 'password' => $newPassword])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('a realm admin can change another users password in their own realm', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);
    $newPassword = 'Zad1!'.bin2hex(random_bytes(6));

    Livewire::test(ChangePassword::class, ['realm' => $community, 'username' => $target->username])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    auth()->logout();
    $this->post('/'.$community->getShortCode().'/login', ['uid' => $target->username, 'password' => $newPassword])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('a realm admin cannot change a password in a different realm', function (): void {
    $adminRealm = newCommunity();
    $otherRealm = newCommunity();
    $target = TestLdap::member($otherRealm);
    actingAsAdmin($adminRealm);

    Livewire::test(ChangePassword::class, ['realm' => $otherRealm, 'username' => $target->username])
        ->assertForbidden();
});

test('a user cannot change someone else password', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(ChangePassword::class, ['realm' => $community, 'username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('the new password must meet the policy and be confirmed', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(ChangePassword::class, ['realm' => $community, 'username' => $user->username])
        ->set('password', 'weak')
        ->set('password_confirmation', 'different')
        ->call('save')
        ->assertHasErrors('password');
});
