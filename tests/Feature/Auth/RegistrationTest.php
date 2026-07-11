<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Registration is LDAP-backed: the RegisterUser Livewire component validates the
 * email domain against the registerable domains in LDAP and, on success, creates
 * the account in the directory and logs the user in. The seeded example.test
 * domain belongs to the "testcom" community (docker/openldap/bootstrap).
 */
beforeEach(function (): void {
    // Unique per run so repeated local runs don't collide; removed in afterEach.
    $this->username = 'phpreg'.bin2hex(random_bytes(4));
    $this->password = 'Aa1!'.bin2hex(random_bytes(8));
    purgeRegisteredUser($this->username);
});

afterEach(function (): void {
    purgeRegisteredUser($this->username);
});

/** Detach from the community and delete the LDAP user a registration created. */
function purgeRegisteredUser(string $username): void
{
    $ldapUser = LdapUser::findByUsername($username);
    if ($ldapUser === null) {
        return;
    }
    Community::findByUid('testcom')?->membersGroup()?->members()->detach($ldapUser);
    $ldapUser->delete();
}

test('registration screen can be rendered and livewire is there', function (): void {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSeeLivewire('register-user');
});

test('a valid registration creates the account and prompts email verification', function (): void {
    // Fake only Registered so the email-verification listener stays quiet, while
    // any other listeners still fire.
    Event::fake([Registered::class]);

    // Set email first: the component's updatedEmail() hook pre-fills the name
    // fields from the address, so our explicit values must be set afterwards.
    Livewire::test('register-user')
        ->set('email', $this->username.'@example.test')
        ->set('first_name', 'Happy')
        ->set('last_name', 'Path')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    // Account exists in the directory with the details we submitted.
    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($this->username.'@example.test')
        ->and($ldapUser->getFirstAttribute('givenName'))->toBe('Happy');

    // The registration flow was kicked off. NOTE: the component tries to log the
    // user straight in, but Auth::attempt() there is passed a positional array
    // ([$username, $password]) instead of keyed credentials (['uid' => ...]), so
    // it does not actually authenticate — the user reaches verification.notice as
    // a guest. Logging in via /login (keyed) works and is covered by
    // LdapAuthenticationTest. Asserting the created account + event keeps this
    // test honest without pinning that broken auto-login.
    Event::assertDispatched(Registered::class);
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
