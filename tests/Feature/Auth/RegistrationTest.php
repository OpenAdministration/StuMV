<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User as DbUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Registration is LDAP-backed and realm-bound: the RegisterUser Livewire
 * component (mounted at {realm}/register) validates the email domain against
 * that specific realm's registerable domains and, on success, creates the
 * account directly under its People branch and logs the user in. There is no
 * dedicated register-realm picker anymore - /register just redirects to the
 * shared /login picker, whose realm-specific login page links onward to
 * {realm}/register. The seeded example.test domain belongs to the "testcom"
 * community (docker/openldap/bootstrap).
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

/** Delete the LDAP user a registration created. */
function purgeRegisteredUser(string $username): void
{
    LdapUser::findByUsername($username)?->delete();
}

test('the registration picker redirects to the shared login picker', function (): void {
    $this->get('/register')->assertRedirect(route('login'));
});

test('the realm-specific registration form can be reached directly', function (): void {
    $this->get(route('realm.register', ['realm' => 'testcom']))
        ->assertStatus(200)
        ->assertSeeLivewire('register-user');
});

test('the admin realm has no registration form', function (): void {
    $this->get(route('realm.register', ['realm' => 'admin']))->assertNotFound();
});

test('a valid registration persists every submitted field, joins the community and requires email verification', function (): void {
    // Fake only Registered so the email-verification listener stays quiet, while
    // the LDAP auth events that Auth::validate() relies on still fire.
    Event::fake([Registered::class]);

    $community = Community::findByUid('testcom');
    $email = $this->username.'@example.test';

    // Set email first: the component's updatedEmail() hook pre-fills the name
    // fields from the address, so our explicit values must be set afterwards.
    Livewire::test('register-user', ['realm' => $community])
        ->set('email', $email)
        ->set('first_name', 'Happy')
        ->set('last_name', 'Path')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realm.login', ['realm' => 'testcom']));

    // Every attribute the component writes must round-trip to the directory.
    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('uid'))->toBe($this->username)
        ->and($ldapUser->getFirstAttribute('givenName'))->toBe('Happy')
        ->and($ldapUser->getFirstAttribute('sn'))->toBe('Path')
        ->and($ldapUser->getFirstAttribute('cn'))->toBe('Happy Path')
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($email);

    // The account joined the community that owns the registration domain -
    // membership is the location itself, so its entry lives directly under
    // that community's own People branch.
    expect($ldapUser->getDn())->toEndWith(','.$community->peopleDn());

    // ...and the database entry records that community as its realm.
    expect(DbUser::where('username', $this->username)->value('realm'))->toBe('testcom');

    // ...the Registered event fired (which also proves the password was
    // stored in a form LDAP can bind against, since Auth::validate() checks
    // it), but the freshly registered user is not logged in - they still
    // need to verify their email first.
    Event::assertDispatched(Registered::class);
    $this->assertGuest();
});

test('registration is refused for a domain that is not registerable in this realm', function (): void {
    $community = Community::findByUid('testcom');

    Livewire::test('register-user', ['realm' => $community])
        ->set('first_name', 'Jon')
        ->set('last_name', 'Doe')
        ->set('username', 'jondoe')
        ->set('email', 'jon.doe@not-a-registerable-domain.invalid')
        ->set('password', 'Abcdef1$')
        ->set('password_confirmation', 'Abcdef1$')
        ->call('save')
        ->assertHasErrors('domain');
});

test('registration is refused for a domain registered to a different realm', function (): void {
    $demo = Community::findByUid('demo');

    Livewire::test('register-user', ['realm' => $demo])
        ->set('email', 'someone@example.test') // registered to testcom, not demo
        ->call('save')
        ->assertHasErrors('domain');
});

test('the username may only contain lowercase url-safe characters', function (): void {
    $community = Community::findByUid('testcom');

    Livewire::test('register-user', ['realm' => $community])
        ->set('username', 'Not Allowed!')
        ->call('save')
        ->assertHasErrors('username');
});

test('registration enforces the password policy', function (): void {
    $community = Community::findByUid('testcom');
    $short = 'Ab1$';        // too short
    $noUpper = 'abcdefg1$';  // no uppercase
    $noNumber = 'Abcdefg$';  // no number
    $noSymbol = 'Abcdefg1';  // no symbol
    $valid = 'Abcdef1$';     // satisfies Password::default()

    Livewire::test('register-user', ['realm' => $community])
        ->set('password', $short)->call('save')->assertHasErrors('password')
        ->set('password', $noUpper)->call('save')->assertHasErrors('password')
        ->set('password', $noNumber)->call('save')->assertHasErrors('password')
        ->set('password', $noSymbol)->call('save')->assertHasErrors('password')
        ->set('password', $valid)
        ->set('password_confirmation', $valid)
        ->call('save')->assertHasNoErrors('password');
});
