<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Livewire\Livewire;

/**
 * End-to-end coverage of the LDAP-backed registration + login flow against the
 * dockerised OpenLDAP (see docker/openldap/). Requires the LDAP container to be
 * reachable on the connection configured in .env.testing; CI starts it as a
 * service. The @example.test domain and the "testcom" community it belongs to
 * are part of the container's seed data (docker/openldap/bootstrap/10-sample.ldif).
 */
const DOMAIN = 'example.test';

/**
 * Register the per-test user through the RegisterUser Livewire component,
 * asserting the component reports no validation errors.
 */
function registerLdapUser(string $username, string $password): void
{
    Livewire::test('register-user', ['realm' => Community::findByUid('testcom')])
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('username', $username)
        ->set('email', $username.'@'.DOMAIN)
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('save')
        ->assertHasNoErrors();
}

/** Remove the per-test user from LDAP. */
function purgeLdapUser(string $username): void
{
    LdapUser::findByUsername($username)?->delete();
}

beforeEach(function (): void {
    // Unique per run so repeated local runs don't collide; removed in afterEach.
    $this->username = 'phptest'.bin2hex(random_bytes(4));
    // Random per run: satisfies Password::defaults() and is not a leaked password.
    $this->password = 'Aa1!'.bin2hex(random_bytes(8));
    purgeLdapUser($this->username);
});

afterEach(function (): void {
    purgeLdapUser($this->username);
});

test('a user can register into ldap', function (): void {
    registerLdapUser($this->username, $this->password);

    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($this->username.'@'.DOMAIN);
});

test('a registered user becomes a member of the community', function (): void {
    registerLdapUser($this->username, $this->password);

    $community = Community::findByUid('testcom');
    $ldapUser = LdapUser::findByUsername($this->username);

    expect($ldapUser->getDn())->toEndWith(','.$community->peopleDn());
});

test('a registered user can log in', function (): void {
    registerLdapUser($this->username, $this->password);
    auth()->logout();
    $this->assertGuest();

    $this->post('/testcom/login', ['uid' => $this->username, 'password' => $this->password])
        ->assertSessionHasNoErrors()
        ->assertRedirect(RouteServiceProvider::home('testcom'));

    $this->assertAuthenticated();
    expect(auth()->user())->toBeInstanceOf(User::class);
});

test('a registered user can log in with their email address', function (): void {
    registerLdapUser($this->username, $this->password);
    auth()->logout();

    // LoginRequest routes an email-shaped uid to the `mail` attribute.
    $this->post('/testcom/login', ['uid' => $this->username.'@'.DOMAIN, 'password' => $this->password])
        ->assertSessionHasNoErrors()
        ->assertRedirect(RouteServiceProvider::home('testcom'));

    $this->assertAuthenticated();
});

test('login is rejected with a wrong password', function (): void {
    registerLdapUser($this->username, $this->password);
    auth()->logout();

    $this->post('/testcom/login', ['uid' => $this->username, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('uid');

    $this->assertGuest();
});

test('a locked user cannot log in even with the correct password', function (): void {
    registerLdapUser($this->username, $this->password);
    auth()->logout();

    $ldapUser = LdapUser::findByUsername($this->username);
    $ldapUser->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldapUser->save();

    $this->post('/testcom/login', ['uid' => $this->username, 'password' => $this->password])
        ->assertSessionHasErrors('uid');

    $this->assertGuest();
});
