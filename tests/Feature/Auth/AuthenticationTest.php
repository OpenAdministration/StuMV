<?php

test('login screen can be rendered', function (): void {
    $this->get('/login')->assertStatus(200);
});

test('login is rejected for an unknown user', function (): void {
    // The login form posts `uid` (see LoginRequest); no such user exists in LDAP.
    $this->post('/login', [
        'uid' => 'nobody-'.uniqid(),
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('uid');

    $this->assertGuest();
});

test('login validation requires a uid and password', function (): void {
    $this->post('/login', [])->assertSessionHasErrors(['uid', 'password']);

    $this->assertGuest();
});
