<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login screen can be rendered', function (): void {
    $this->get('/login')->assertStatus(200);
});

test('login is rejected for an unknown user', function (): void {
    $community = newCommunity();

    // The login form posts `uid` (see LoginRequest); no such user exists in LDAP.
    $this->post('/'.$community->getShortCode().'/login', [
        'uid' => 'nobody-'.uniqid(),
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('uid');

    $this->assertGuest();
});

test('login validation requires a uid and password', function (): void {
    $community = newCommunity();

    $this->post('/'.$community->getShortCode().'/login', [])->assertSessionHasErrors(['uid', 'password']);

    $this->assertGuest();
});

test('logging out redirects to that users own realm login page', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $this->post('/logout')->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    $this->assertGuest();
});

test('the logout confirmation page defaults its redirect to that users own realm login page', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $this->get('/logout')->assertSee(
        urlencode(route('realm.login', ['realm' => $community->getShortCode()])),
        false,
    );
});
