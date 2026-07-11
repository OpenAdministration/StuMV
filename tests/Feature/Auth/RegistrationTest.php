<?php

use Livewire\Livewire;

/**
 * Registration is LDAP-backed: the RegisterUser Livewire component validates the
 * email domain against the registerable domains in LDAP and, on success, creates
 * the account in the directory. The full happy path (create + login) lives in
 * LdapAuthenticationTest; here we cover the screen and the validation guards,
 * none of which persist a user.
 */
test('registration screen can be rendered and livewire is there', function (): void {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSeeLivewire('register-user');
});

test('registration is refused for a domain that is not registerable', function (): void {
    Livewire::test('register-user')
        ->set('first_name', 'Jon')
        ->set('last_name', 'Doe')
        ->set('username', 'jondoe')
        ->set('email', 'jon.doe@not-a-registerable-domain.invalid')
        ->set('password', 'Abcdef1$')
        ->set('password_confirmation', 'Abcdef1$')
        ->call('save')
        ->assertHasErrors('domain');
});

test('the username may only contain lowercase url-safe characters', function (): void {
    Livewire::test('register-user')
        ->set('username', 'Not Allowed!')
        ->call('save')
        ->assertHasErrors('username');
});

test('registration enforces the password policy', function (): void {
    $short = 'Ab1$';        // too short
    $noUpper = 'abcdefg1$';  // no uppercase
    $noNumber = 'Abcdefg$';  // no number
    $noSymbol = 'Abcdefg1';  // no symbol
    $valid = 'Abcdef1$';     // satisfies Password::default()

    Livewire::test('register-user')
        ->set('password', $short)->call('save')->assertHasErrors('password')
        ->set('password', $noUpper)->call('save')->assertHasErrors('password')
        ->set('password', $noNumber)->call('save')->assertHasErrors('password')
        ->set('password', $noSymbol)->call('save')->assertHasErrors('password')
        ->set('password', $valid)
        ->set('password_confirmation', $valid)
        ->call('save')->assertHasNoErrors('password');
});
