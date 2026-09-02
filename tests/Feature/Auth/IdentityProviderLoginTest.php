<?php

use App\Ldap\User as LdapUser;
use App\Models\IdentityProviderSession;
use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use App\Support\OidcProviderFactory;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\OAuth2\Client\Provider\GenericProvider;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * Drives identity-provider.redirect and reads back the state/nonce the
 * controller actually generated - neither is known before this response, so
 * callers need them to sign a matching (or deliberately mismatched, for
 * negative tests) id_token. Assumes the discovery/JWKS endpoints are already
 * faked; startIdentityProviderLogin() is the usual way in, tests that need an
 * unusual JWKS or discovery document fake it themselves and call this.
 *
 * @return array{state: string, nonce: string}
 */
function driveIdentityProviderRedirect(string $realmUid, RealmIdentityProvider $provider): array
{
    $redirect = test()->get(route('identity-provider.redirect', ['realm' => $realmUid, 'provider' => $provider->id]));
    $redirect->assertStatus(302);

    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    return ['state' => $query['state'], 'nonce' => $query['nonce']];
}

/**
 * Fakes the discovery+jwks endpoints and drives the redirect, additionally
 * returning the RSA key backing the faked JWKS. $discovery overrides members
 * of the discovery document (null drops one entirely).
 *
 * @return array{state: string, nonce: string, privateKey: string}
 */
function startIdentityProviderLogin(string $realmUid, RealmIdentityProvider $provider, array $discovery = []): array
{
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks, $discovery);

    return [...driveIdentityProviderRedirect($realmUid, $provider), 'privateKey' => $privateKey];
}

function validIdTokenClaims(RealmIdentityProvider $provider, string $nonce, string $sub): array
{
    return [
        'iss' => $provider->issuer,
        'aud' => $provider->client_id,
        'sub' => $sub,
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
    ];
}

/** $kid null signs without a "kid" header, as providers with a single key may. */
function signIdToken(string $privateKey, array $claims, ?string $kid = 'test-key'): string
{
    return JWT::encode($claims, $privateKey, 'RS256', $kid);
}

/**
 * Wires up the token/userinfo exchange (via a mocked Guzzle client injected
 * through OidcProviderFactory - league/oauth2-client uses its own internal
 * HTTP client, which Http::fake() can't see) and returns the ready-to-GET
 * callback URL. $idToken is nullable so tests can exercise the
 * missing-id_token rejection path.
 */
function identityProviderCallbackUrl(string $realmUid, RealmIdentityProvider $provider, array $login, array $userinfo, ?string $idToken, string $code = 'fake-code'): string
{
    $tokenResponse = [
        'access_token' => 'fake-access-token',
        'token_type' => 'bearer',
        'expires_in' => 3600,
    ];

    if ($idToken !== null) {
        $tokenResponse['id_token'] = $idToken;
    }

    $mockHandler = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode($tokenResponse)),
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

    return route('identity-provider.callback', [
        'realm' => $realmUid,
        'provider' => $provider->id,
        'state' => $login['state'],
        'code' => $code,
    ]);
}

/** Happy-path helper: drives a full login with a validly-signed id_token and returns the callback URL. */
function loginViaIdentityProvider(string $realmUid, RealmIdentityProvider $provider, array $userinfo): string
{
    $login = startIdentityProviderLogin($realmUid, $provider);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']));

    return identityProviderCallbackUrl($realmUid, $provider, $login, $userinfo, $idToken);
}

test('logging in via the identity provider with a matching email logs the existing account in directly', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = [
        'sub' => 'external-123',
        'email' => $existingUser->email,
        'given_name' => 'Ignored',
        'family_name' => 'Ignored',
    ];

    $this->assertGuest();

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('logging in via the identity provider records the sub->session mapping used for back-channel logout', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo));

    $mapping = IdentityProviderSession::where('provider_id', $provider->id)->where('external_sub', 'external-123')->first();

    expect($mapping)->not->toBeNull()
        ->and($mapping->session_id)->toBe(session()->getId());
});

test('logging in via the identity provider with no matching account redirects to the registration-completion step', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-999', 'email' => 'not-yet-registered@example.test'];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('identity-provider.register', ['realm' => $community->getShortCode()]));

    $this->assertGuest();
});

test('a locked account cannot log in via the identity provider even with a matching email', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $ldap = LdapUser::findByUsername($existingUser->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))->assertForbidden();

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
    $userinfo = [
        'sub' => 'external-123',
        'email' => $existingUser->email,
        'groups' => ['stura-member', 'some-unmapped-group'],
    ];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo));

    expect(RoleMembership::where('username', $existingUser->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('an already-authenticated user completing the identity-provider flow as themselves re-syncs a role mapping added since their last login', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    // The mapping is created *before* the round-trip below runs, standing in
    // for "an admin added this after the user's last login" - what matters
    // for this test is that the user is already signed in when they
    // (re-)complete the flow, not when the mapping itself was created.
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email, 'groups' => ['stura-member']];

    $this->actingAs($existingUser);

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
    expect(RoleMembership::where('username', $existingUser->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('an already-authenticated user cannot use the identity-provider flow to switch to a different account', function (): void {
    $community = newCommunity();
    $signedInUser = TestLdap::member($community);
    $otherUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $otherUser->email];

    $this->actingAs($signedInUser);

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertStatus(409);

    $this->assertAuthenticatedAs($signedInUser->fresh());
});

test('an already-authenticated user cannot use the identity-provider flow to register a new, unrelated account', function (): void {
    $community = newCommunity();
    $signedInUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-999', 'email' => 'not-yet-registered@example.test'];

    $this->actingAs($signedInUser);

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertStatus(409);

    $this->assertAuthenticatedAs($signedInUser->fresh());
});

test('an invalid or replayed state is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());

    $this->get(route('identity-provider.callback', [
        'realm' => $community->getShortCode(),
        'provider' => $provider->id,
        'state' => 'not-the-real-state',
        'code' => 'fake-code',
    ]))->assertStatus(400);

    $this->assertGuest();
});

test('identity providers on the login page are listed alphabetically by name, regardless of creation order', function (): void {
    $community = newCommunity();
    makeIdentityProvider($community->getShortCode(), name: 'Zorro SSO');
    makeIdentityProvider($community->getShortCode(), name: 'Apollo Login');
    makeIdentityProvider($community->getShortCode(), name: 'Mercury Auth');

    $this->get(route('realm.login', ['realm' => $community->getShortCode()]))
        ->assertSeeTextInOrder(['Apollo Login', 'Mercury Auth', 'Zorro SSO']);
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

test('a login with no id_token in the token response is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, null);

    $this->get($callbackUrl)->assertStatus(422);

    $this->assertGuest();
});

test('an id_token with a mismatched nonce is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, 'not-the-real-nonce', $userinfo['sub']));
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken);

    $this->get($callbackUrl)->assertStatus(400);

    $this->assertGuest();
});

test('an id_token with the wrong issuer is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iss'] = 'https://not-the-issuer.test';
    $idToken = signIdToken($login['privateKey'], $claims);
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken);

    $this->get($callbackUrl)->assertStatus(400);

    $this->assertGuest();
});

test('an id_token with the wrong audience is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['aud'] = 'someone-elses-client-id';
    $idToken = signIdToken($login['privateKey'], $claims);
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken);

    $this->get($callbackUrl)->assertStatus(400);

    $this->assertGuest();
});

test('an id_token signed by the wrong key is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    [$otherPrivateKey] = makeRsaKeyPairAndJwks('other-key');
    $idToken = signIdToken($otherPrivateKey, validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']), 'other-key');
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken);

    $this->get($callbackUrl)->assertStatus(400);

    $this->assertGuest();
});

test('an id_token can be verified against a JWKS whose keys omit the alg parameter, as authentik does', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    unset($jwks['keys'][0]['alg']);
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $login = [...driveIdentityProviderRedirect($community->getShortCode(), $provider), 'privateKey' => $privateKey];
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']));

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('an id_token signed without a kid header is verified against a single-key JWKS', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']), null);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('an id_token issued a few seconds ahead of this server\'s clock is still accepted', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iat'] = time() + 30;
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('an issuer the discovery document spells with a trailing slash is accepted, as Auth0 does', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider, [
        'issuer' => $provider->issuer.'/',
    ]);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iss'] = $provider->issuer.'/';
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('a discovery document naming a different issuer than the configured one is not trusted', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider, [
        'issuer' => 'https://attacker.example.test',
    ]);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iss'] = 'https://attacker.example.test';
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertStatus(400);

    $this->assertGuest();
});

test('a provider that publishes no userinfo endpoint logs in on the id_token claims alone', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());

    $login = startIdentityProviderLogin($community->getShortCode(), $provider, ['userinfo_endpoint' => null]);
    $claims = validIdTokenClaims($provider, $login['nonce'], 'external-123');
    $claims['email'] = $existingUser->email;
    $idToken = signIdToken($login['privateKey'], $claims);

    // The userinfo response passed here is never fetched - identityProviderCallbackUrl()
    // queues it on the mock handler, but with no endpoint to call it goes unused.
    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, [], $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('a groups claim carried only by the id_token still grants the mapped role', function (): void {
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
    // Entra ID never returns groups from userinfo, and Keycloak's mapper has a
    // separate switch per token - so the claim commonly arrives id_token-only.
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['groups'] = ['stura-member'];
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken));

    expect(RoleMembership::where('username', $existingUser->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('a login whose email the provider reports as unverified is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email, 'email_verified' => false];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertStatus(422);

    $this->assertGuest();
});

test('a login whose email the provider confirms as verified is accepted', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email, 'email_verified' => true];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('the configured scopes are requested, with openid always included', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->update(['scopes' => 'email profile groups']);

    [, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $redirect = $this->get(route('identity-provider.redirect', ['realm' => $community->getShortCode(), 'provider' => $provider->id]));
    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('openid email profile groups');
});

test('cancelling at the identity provider returns to the login page instead of an error', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);

    $this->get(route('identity-provider.callback', [
        'realm' => $community->getShortCode(),
        'provider' => $provider->id,
        'state' => $login['state'],
        'error' => 'access_denied',
    ]))->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    $this->assertGuest();
});

test('a userinfo response whose sub does not match the id_token is rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'a-different-subject', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], 'external-123'));
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken);

    $this->get($callbackUrl)->assertStatus(422);

    $this->assertGuest();
});
