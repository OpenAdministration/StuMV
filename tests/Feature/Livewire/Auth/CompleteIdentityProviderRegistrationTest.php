<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Auth\CompleteSsoRegistration;
use App\Models\RoleMembership;
use App\Models\User as DbUser;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * Mirrors tests/Feature/Auth/RegistrationTest.php's conventions: a real LDAP
 * account is created under the realm's People branch, so usernames are
 * randomised per test and purged in afterEach rather than relying on
 * RefreshDatabase (which wouldn't touch the directory anyway).
 */
beforeEach(function (): void {
    $this->username = 'ssoreg'.bin2hex(random_bytes(4));
    purgeSsoRegisteredUser($this->username);
});

afterEach(function (): void {
    purgeSsoRegisteredUser($this->username);
});

function purgeSsoRegisteredUser(string $username): void
{
    LdapUser::findByUsername($username)?->delete();
}

/** Stash a "sso_pending" session payload the same way OidcLoginController::callback does for a brand-new account. */
function stashSsoPending(string $realmUid, ?int $providerId, string $email, array $overrides = []): void
{
    test()->withSession(['sso_pending' => array_merge([
        'realm' => $realmUid,
        'provider_id' => $providerId,
        'email' => $email,
        'given_name' => 'Ada',
        'family_name' => 'Lovelace',
        'claims' => ['email' => $email],
    ], $overrides)]);
}

test('the completion form is pre-filled from the stashed claims', function (): void {
    $community = newCommunity();
    $email = $this->username.'@example.test';
    stashSsoPending($community->getShortCode(), null, $email);

    Livewire::test(CompleteSsoRegistration::class, ['realm' => $community])
        ->assertSet('email', $email)
        ->assertSet('first_name', 'Ada')
        ->assertSet('last_name', 'Lovelace');
});

test('completing registration creates exactly one LDAP entry and database account, verified and logged in', function (): void {
    $community = newCommunity();
    $email = $this->username.'@example.test';
    stashSsoPending($community->getShortCode(), null, $email);

    Livewire::test(CompleteSsoRegistration::class, ['realm' => $community])
        ->set('username', $this->username)
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($email)
        ->and($ldapUser->getFirstAttribute('givenName'))->toBe('Ada')
        ->and($ldapUser->getFirstAttribute('sn'))->toBe('Lovelace')
        ->and($ldapUser->getDn())->toEndWith(','.$community->peopleDn());

    $dbUser = DbUser::where('username', $this->username)->first();
    expect($dbUser)->not->toBeNull()
        ->and($dbUser->realm)->toBe($community->getShortCode())
        ->and($dbUser->email_verified_at)->not->toBeNull()
        ->and(DbUser::where('email', $email)->count())->toBe(1);

    $this->assertAuthenticatedAs($dbUser);
});

test('completing registration also applies role mappings from the stashed claims', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeSsoProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    $email = $this->username.'@example.test';
    stashSsoPending($community->getShortCode(), $provider->id, $email, [
        'claims' => ['email' => $email, 'groups' => ['stura-member']],
    ]);

    Livewire::test(CompleteSsoRegistration::class, ['realm' => $community])
        ->set('username', $this->username)
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->call('save')
        ->assertHasNoErrors();

    expect(RoleMembership::where('username', $this->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('the completion form requires a username, first and last name', function (): void {
    $community = newCommunity();
    stashSsoPending($community->getShortCode(), null, $this->username.'@example.test');

    Livewire::test(CompleteSsoRegistration::class, ['realm' => $community])
        ->set('username', '')
        ->set('first_name', '')
        ->set('last_name', '')
        ->call('save')
        ->assertHasErrors(['username' => 'required', 'first_name' => 'required', 'last_name' => 'required']);
});

test('the username may only contain lowercase url-safe characters', function (): void {
    $community = newCommunity();
    stashSsoPending($community->getShortCode(), null, $this->username.'@example.test');

    Livewire::test(CompleteSsoRegistration::class, ['realm' => $community])
        ->set('username', 'Not Allowed!')
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->call('save')
        ->assertHasErrors('username');
});

test('the completion step cannot be reached without a pending SSO login', function (): void {
    $community = newCommunity();

    $this->get(route('sso.register', ['realm' => $community->getShortCode()]))->assertNotFound();
});

test('a pending SSO login for a different realm cannot be completed here', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    stashSsoPending($otherCommunity->getShortCode(), null, $this->username.'@example.test');

    $this->get(route('sso.register', ['realm' => $community->getShortCode()]))->assertNotFound();
});
