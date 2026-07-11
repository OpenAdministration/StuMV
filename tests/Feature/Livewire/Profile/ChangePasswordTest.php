<?php

use App\Livewire\ChangePassword;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a user can change their own password and log in with it', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $newPassword = 'Znew1!'.bin2hex(random_bytes(6));

    Livewire::test(ChangePassword::class, ['username' => $user->username])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    // Prove the directory really accepts the new password on bind.
    auth()->logout();
    $this->post('/login', ['uid' => $user->username, 'password' => $newPassword])
        ->assertSessionHasNoErrors()
        ->assertRedirect(RouteServiceProvider::home());
    $this->assertAuthenticated();
});

test('a super admin can change another user password', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();
    $newPassword = 'Zsu1!'.bin2hex(random_bytes(6));

    Livewire::test(ChangePassword::class, ['username' => $target->username])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('save')
        ->assertHasNoErrors();

    auth()->logout();
    $this->post('/login', ['uid' => $target->username, 'password' => $newPassword])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('a user cannot change someone else password', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(ChangePassword::class, ['username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('the new password must meet the policy and be confirmed', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(ChangePassword::class, ['username' => $user->username])
        ->set('password', 'weak')
        ->set('password_confirmation', 'different')
        ->call('save')
        ->assertHasErrors('password');
});
