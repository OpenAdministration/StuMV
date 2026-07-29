<?php

use App\Ldap\User as LdapUser;
use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use App\Support\OidcProviderFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\GenericProvider;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * Stubs the external identity provider's discovery/token/userinfo endpoints so
 * OidcLoginController can run its real authorization-code exchange (via
 * league/oauth2-client) against fake, deterministic responses instead of a
 * real IdP. The discovery document is fetched via Laravel's Http facade
 * (faked normally), but the token/userinfo exchange happens through
 * league/oauth2-client's own internal Guzzle client, which Http::fake() can't
 * see - so that part is mocked via a Guzzle MockHandler injected through
 * OidcProviderFactory instead.
 */
function fakeIdentityProvider(string $issuer, array $userinfo): void
{
    Http::fake([
        $issuer.'/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => $issuer.'/authorize',
            'token_endpoint' => $issuer.'/token',
            'userinfo_endpoint' => $issuer.'/userinfo',
        ]),
    ]);

    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'fake-access-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($userinfo)),
    ]);
    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler)]);

    app()->bind(OidcProviderFactory::class, fn () => new class($guzzle) extends OidcProviderFactory
    {
        public function __construct(private readonly Client $guzzle) {}

        public function make(array $options): GenericProvider
        {
            return new GenericProvider($options, ['httpClient' => $this->guzzle]);
        }
    });
}

/** Hits identity-provider.redirect, pulls the state it generated out of the redirect URL, and returns the matching identity-provider.callback URL. */
function identityProviderCallbackUrl(string $realmUid, RealmIdentityProvider $provider, string $code = 'fake-code'): string
{
    $redirect = test()->get(route('identity-provider.redirect', ['realm' => $realmUid, 'provider' => $provider->id]));
    $redirect->assertStatus(302);

    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    return route('identity-provider.callback', [
        'realm' => $realmUid,
        'provider' => $provider->id,
        'state' => $query['state'],
        'code' => $code,
    ]);
}

test('logging in via the identity provider with a matching email logs the existing account in directly', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    fakeIdentityProvider($provider->issuer, [
        'sub' => 'external-123',
        'email' => $existingUser->email,
        'given_name' => 'Ignored',
        'family_name' => 'Ignored',
    ]);

    $this->assertGuest();

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('logging in via the identity provider with no matching account redirects to the registration-completion step', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    fakeIdentityProvider($provider->issuer, [
        'sub' => 'external-999',
        'email' => 'not-yet-registered@example.test',
    ]);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider))
        ->assertRedirect(route('identity-provider.register', ['realm' => $community->getShortCode()]));

    $this->assertGuest();
});

test('a locked account cannot log in via the identity provider even with a matching email', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    fakeIdentityProvider($provider->issuer, [
        'sub' => 'external-123',
        'email' => $existingUser->email,
    ]);

    $ldap = LdapUser::findByUsername($existingUser->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider))->assertForbidden();

    $this->assertGuest();
});

test('a matching login grants roles mapped from the returned groups claim', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    fakeIdentityProvider($provider->issuer, [
        'sub' => 'external-123',
        'email' => $existingUser->email,
        'groups' => ['stura-member', 'some-unmapped-group'],
    ]);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider));

    expect(RoleMembership::where('username', $existingUser->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('an invalid or replayed state is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    fakeIdentityProvider($provider->issuer, ['sub' => 'x', 'email' => 'someone@example.test']);

    $this->get(route('identity-provider.callback', [
        'realm' => $community->getShortCode(),
        'provider' => $provider->id,
        'state' => 'not-the-real-state',
        'code' => 'fake-code',
    ]))->assertStatus(400);

    $this->assertGuest();
});

test('extra authorize params configured on the provider are appended to the authorize redirect', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), extraAuthorizeParams: ['kc_idp_hint' => 'my-upstream-idp']);
    fakeIdentityProvider($provider->issuer, ['sub' => 'x', 'email' => 'someone@example.test']);

    $redirect = $this->get(route('identity-provider.redirect', ['realm' => $community->getShortCode(), 'provider' => $provider->id]));

    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['kc_idp_hint'] ?? null)->toBe('my-upstream-idp');
});

test('a disabled identity provider cannot be used to log in', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), enabled: false);

    $this->get(route('identity-provider.redirect', ['realm' => $community->getShortCode(), 'provider' => $provider->id]))
        ->assertNotFound();
});

test('another realm\'s identity provider cannot be used to log in through this realm', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeIdentityProvider($otherCommunity->getShortCode());

    $this->get(route('identity-provider.redirect', ['realm' => $community->getShortCode(), 'provider' => $provider->id]))
        ->assertNotFound();
});
