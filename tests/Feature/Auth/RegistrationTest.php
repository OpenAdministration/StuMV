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

test('a valid registration persists every submitted field, joins the community and logs in', function (): void {
    // Fake only Registered so the email-verification listener stays quiet, while
    // the LDAP auth events that Auth::attempt() relies on still fire.
    Event::fake([Registered::class]);

    $email = $this->username.'@example.test';

    // Set email first: the component's updatedEmail() hook pre-fills the name
    // fields from the address, so our explicit values must be set afterwards.
    Livewire::test('register-user')
        ->set('email', $email)
        ->set('first_name', 'Happy')
        ->set('last_name', 'Path')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    // Every attribute the component writes must round-trip to the directory.
    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('uid'))->toBe($this->username)
        ->and($ldapUser->getFirstAttribute('givenName'))->toBe('Happy')
        ->and($ldapUser->getFirstAttribute('sn'))->toBe('Path')
        ->and($ldapUser->getFirstAttribute('cn'))->toBe('Happy Path')
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($email);

    // The account joined the community that owns the registration domain...
    $members = Community::findByUid('testcom')->membersGroup()->members()->get()
        ->map(fn ($member) => $member->getFirstAttribute('uid'));
    expect($members)->toContain($this->username);

    // ...the Registered event fired, and the user is logged straight in (which
    // also proves the password was stored in a form LDAP can bind against).
    Event::assertDispatched(Registered::class);
    $this->assertAuthenticated();
    expect(auth()->user()->username)->toBe($this->username);
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
