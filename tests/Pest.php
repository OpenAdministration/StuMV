<?php

use App\Ldap\Community;
use App\Models\PassportClient;
use App\Models\RealmSsoProvider;
use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full Laravel application (via Tests\TestCase) so they
| can hit routes, Livewire components, the database and the dockerised LDAP.
| Unit tests stay on the plain PHPUnit test case: they exercise pure logic
| (model configuration, value objects) without booting the framework.
|
| After every feature test we purge any throwaway LDAP users/memberships that
| the actingAs* helpers below created, so runs never leak directory state.
|
*/

pest()->extend(TestCase::class)
    ->afterEach(fn () => TestLdap::cleanup())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Authenticated user helpers
|--------------------------------------------------------------------------
|
| Each helper creates a real, self-cleaning LDAP-backed user at the requested
| authorization level, logs it in, and returns the App\Models\User so it can be
| reused (e.g. `$this->actingAs(actingAsAdmin())->get(...)`). "member",
| "moderator" and "admin" are scoped to a community — pass a uid string (default:
| the seeded "demo" community) or a Community instance (e.g. one from
| newCommunity()); "superAdmin" is global. See Tests\Support\TestLdap.
|
*/

function actingAsMember(string|Community $community = 'demo'): User
{
    return tap(TestLdap::member($community), fn (User $user) => test()->actingAs($user));
}

function actingAsModerator(string|Community $community = 'demo'): User
{
    return tap(TestLdap::moderator($community), fn (User $user) => test()->actingAs($user));
}

function actingAsAdmin(string|Community $community = 'demo'): User
{
    return tap(TestLdap::admin($community), fn (User $user) => test()->actingAs($user));
}

function actingAsSuperAdmin(): User
{
    return tap(TestLdap::superAdmin(), fn (User $user) => test()->actingAs($user));
}

/*
|--------------------------------------------------------------------------
| Authenticated third-party application helper
|--------------------------------------------------------------------------
|
| The directory API (routes/api.php) authenticates third-party applications
| via the OAuth2 client-credentials grant instead of a delegated user login -
| there is no human behind the token, only a client bound to one community.
| This registers a real client-credentials PassportClient for that community
| and simulates a valid client-credentials token with the given scopes.
|
*/

function actingAsDirectoryClient(Community $community, array $scopes = []): PassportClient
{
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Test Client');
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    Passport::actingAsClient($client, $scopes);

    return $client;
}

/*
|--------------------------------------------------------------------------
| LDAP structure helpers
|--------------------------------------------------------------------------
|
| Build a self-cleaning directory structure for a test instead of relying on
| the hand-seeded communities. All entries are removed after the test.
|
*/

function newCommunity(?string $uid = null): Community
{
    return TestLdap::makeCommunity($uid);
}

/*
|--------------------------------------------------------------------------
| External identity provider helper
|--------------------------------------------------------------------------
|
| Creates a real (but never contacted in most tests) RealmSsoProvider row
| pointing at a fake issuer - see Tests\Support\FakesSso for stubbing the
| actual discovery/token/userinfo HTTP exchange in login-flow tests.
|
*/

function makeSsoProvider(string $realmUid, string $name = 'Test IdP', bool $enabled = true): RealmSsoProvider
{
    return RealmSsoProvider::create([
        'realm' => $realmUid,
        'name' => $name,
        'issuer' => 'https://idp.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'groups_claim' => 'groups',
        'enabled' => $enabled,
    ]);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidUuid', fn () => $this->toMatch('/^[0-9a-f-]{36}$/i'));
