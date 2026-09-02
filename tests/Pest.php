<?php

use App\Ldap\Community;
use App\Models\PassportClient;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
| Creates a real (but never contacted in most tests) RealmIdentityProvider
| row pointing at a fake issuer - login-flow tests stub the actual
| discovery/token/userinfo HTTP exchange themselves via Http::fake().
|
*/

function makeIdentityProvider(string $realmUid, string $name = 'Test IdP', bool $enabled = true): RealmIdentityProvider
{
    return RealmIdentityProvider::create([
        'realm' => $realmUid,
        'name' => $name,
        'issuer' => 'https://idp.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'groups_claim' => 'groups',
        'enabled' => $enabled,
    ]);
}

/**
 * Generates a fresh RSA keypair and the matching JWKS document, so a test can
 * sign an id_token/logout_token the way a real identity provider would and
 * have OidcLoginController verify it against that JWKS.
 *
 * @return array{0: string, 1: array}
 */
function makeRsaKeyPairAndJwks(string $kid = 'test-key'): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($resource, $privateKeyPem);
    $details = openssl_pkey_get_details($resource);

    $base64Url = fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    $jwks = [
        'keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $base64Url($details['rsa']['n']),
            'e' => $base64Url($details['rsa']['e']),
        ]],
    ];

    return [$privateKeyPem, $jwks];
}

/**
 * Creates a session row directly, so a test can set up several at once and
 * then assert which of them a logout ended. Going through
 * session()->getHandler()->write() cannot do that: its `exists` flag latches
 * true after the first write, quietly turning every later one into an update
 * of a row that isn't there.
 */
function writeTestSession(string $sessionId, array $data): void
{
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'payload' => base64_encode(serialize($data)),
        'last_activity' => time(),
    ]);
}

/**
 * Fakes the discovery document (including jwks_uri) and the JWKS endpoint
 * itself. $discovery overrides individual members of the document; a member
 * set to null there is dropped from it entirely, so a test can model a
 * provider that doesn't advertise one.
 */
function fakeIdentityProviderJwks(string $issuer, array $jwks, array $discovery = []): void
{
    $document = array_filter(array_merge([
        'authorization_endpoint' => $issuer.'/authorize',
        'token_endpoint' => $issuer.'/token',
        'userinfo_endpoint' => $issuer.'/userinfo',
        'jwks_uri' => $issuer.'/jwks',
    ], $discovery), fn ($value): bool => $value !== null);

    Http::fake([
        $issuer.'/.well-known/openid-configuration' => Http::response($document),
        $issuer.'/jwks' => Http::response($jwks),
    ]);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidUuid', fn () => $this->toMatch('/^[0-9a-f-]{36}$/i'));
