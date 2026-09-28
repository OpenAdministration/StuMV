<?php

use App\Ldap\User as LdapUser;
use App\Models\IdentityProviderSession;
use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use Firebase\JWT\JWT;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * Drives identity-provider.redirect and reads back the state/nonce the
 * controller actually generated - neither is known before this response, so
 * callers need them to sign a matching (or deliberately mismatched, for
 * negative tests) id_token. Assumes the discovery endpoint is already faked
 * (fakeIdentityProviderHttp()); startIdentityProviderLogin() is the usual way
 * in, tests that need an unusual discovery document fake it themselves and
 * call this.
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
 * Fakes the discovery endpoint and drives the redirect, additionally
 * returning the RSA key + JWKS document backing it (queued by
 * identityProviderCallbackUrl() at the right point in the flow - see its own
 * docblock) and the shared mocked Guzzle handler. $discovery overrides
 * members of the discovery document (null drops one entirely).
 *
 * @return array{state: string, nonce: string, privateKey: string, jwks: array, mockHandler: MockHandler, handlerStack: HandlerStack}
 */
function startIdentityProviderLogin(string $realmUid, RealmIdentityProvider $provider, array $discovery = []): array
{
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    $http = fakeIdentityProviderHttp($provider->issuer, $discovery);

    $login = driveIdentityProviderRedirect($realmUid, $provider);

    return [...$login, 'privateKey' => $privateKey, 'jwks' => $jwks, ...$http];
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
 * Appends the token/JWKS/userinfo exchange onto the flow's shared mocked
 * Guzzle handler (see startIdentityProviderLogin()) and returns the
 * ready-to-GET callback URL. $idToken is nullable so tests can exercise the
 * missing-id_token rejection path. Queued in the order Provider::user()
 * actually requests them: the token endpoint first, then (only once, since
 * $login['jwks']'s "test-key" kid is always the one the id_token was signed
 * with) the JWKS to verify the id_token it just got back, then userinfo.
 */
function identityProviderCallbackUrl(string $realmUid, RealmIdentityProvider $provider, array $login, array $userinfo, ?string $idToken, string $code = 'fake-code', ?array &$requestHistory = null): string
{
    $tokenResponse = [
        'access_token' => 'fake-access-token',
        'token_type' => 'bearer',
        'expires_in' => 3600,
    ];

    if ($idToken !== null) {
        $tokenResponse['id_token'] = $idToken;
    }

    if ($requestHistory !== null) {
        $login['handlerStack']->push(Middleware::history($requestHistory));
    }

    $login['mockHandler']->append(new Response(200, ['Content-Type' => 'application/json'], json_encode($tokenResponse)));

    if (isset($login['jwks'])) {
        $login['mockHandler']->append(new Response(200, ['Content-Type' => 'application/json'], json_encode($login['jwks'])));
    }

    $login['mockHandler']->append(new Response(200, ['Content-Type' => 'application/json'], json_encode($userinfo)));

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

test('a login asserting one of the account\'s additional email addresses finds that account', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());

    $ldapUser = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $existingUser->username)->first();
    $ldapUser->addAdditionalEmail('alias@example.test');
    $ldapUser->save();

    $userinfo = ['sub' => 'external-123', 'email' => 'alias@example.test'];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('adding an additional address leaves the primary one matching as before', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());

    $ldapUser = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $existingUser->username)->first();
    $ldapUser->addAdditionalEmail('alias@example.test');
    $ldapUser->save();

    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('a login for an address held by an account that has never signed in is rejected, not turned into a second account', function (): void {
    $community = newCommunity();
    // LDAP entry only, no database row - that row is what a first sign-in
    // creates, and without it the account cannot be matched.
    $ldapUser = TestLdap::makeUser(community: $community);
    $provider = makeIdentityProvider($community->getShortCode());

    $userinfo = ['sub' => 'external-123', 'email' => $ldapUser->getFirstAttribute('mail')];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertStatus(409);

    $this->assertGuest();
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

    $this->get($callbackUrl)->assertStatus(400);

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

    // The signing kid ("other-key") isn't in $login['jwks'], so the package
    // assumes a key rotation and force-refreshes the JWKS once more before
    // giving up - queue a second copy to satisfy that retry, instead of
    // going through identityProviderCallbackUrl()'s single-jwks assumption.
    $login['mockHandler']->append(
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'fake-access-token', 'token_type' => 'bearer', 'expires_in' => 3600, 'id_token' => $idToken])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($login['jwks'])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($login['jwks'])),
    );

    $this->get(route('identity-provider.callback', [
        'realm' => $community->getShortCode(),
        'provider' => $provider->id,
        'state' => $login['state'],
        'code' => 'fake-code',
    ]))->assertStatus(400);

    $this->assertGuest();
});

test('an id_token can be verified against a JWKS whose keys omit the alg parameter, as authentik does', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    unset($login['jwks']['keys'][0]['alg']);

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

test('an id_token naming the issuer without its scheme is accepted, as Google returns it', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iss'] = preg_replace('#^https://#', '', $provider->issuer);
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('a scheme-less issuer belonging to someone else is still rejected', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']);
    $claims['iss'] = 'attacker.example.test';
    $idToken = signIdToken($login['privateKey'], $claims);

    $this->get(identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken))
        ->assertStatus(400);

    $this->assertGuest();
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

test('a failing userinfo endpoint does not lose the login, the id_token claims carry it', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $claims = validIdTokenClaims($provider, $login['nonce'], 'external-123');
    $claims['email'] = $existingUser->email;
    $idToken = signIdToken($login['privateKey'], $claims);

    // With the email already on the id_token, the package never even attempts
    // the userinfo call (see Provider::hasEmptyEmail()) - it's queued here
    // purely to prove it's never consumed. Entra ID, for comparison, hosts
    // userinfo on Microsoft Graph, which answers 401 once an extra resource
    // scope retargets the access token; either way, the login must not be lost.
    $login['mockHandler']->append(
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'fake-access-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'id_token' => $idToken,
        ])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($login['jwks'])),
        new Response(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'InvalidAuthenticationToken'])),
    );

    $this->get(route('identity-provider.callback', [
        'realm' => $community->getShortCode(),
        'provider' => $provider->id,
        'state' => $login['state'],
        'code' => 'fake-code',
    ]))->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

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

test('a provider with email verification disabled accepts an unverified address', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->update(['enforce_email_verified' => false]);
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email, 'email_verified' => false];

    $this->get(loginViaIdentityProvider($community->getShortCode(), $provider, $userinfo))
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $this->assertAuthenticatedAs($existingUser->fresh());
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

test('client credentials go in the request body by default', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']));
    $history = [];
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken, 'fake-code', $history);

    $this->get($callbackUrl);

    $tokenRequest = $history[0]['request'];
    expect($tokenRequest->hasHeader('Authorization'))->toBeFalse()
        ->and((string) $tokenRequest->getBody())->toContain('client_secret=client-secret');
});

test('discovery naming client_secret_basic as the supported auth method is honored', function (): void {
    $community = newCommunity();
    $existingUser = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $userinfo = ['sub' => 'external-123', 'email' => $existingUser->email];

    $login = startIdentityProviderLogin($community->getShortCode(), $provider, [
        'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
    ]);
    $idToken = signIdToken($login['privateKey'], validIdTokenClaims($provider, $login['nonce'], $userinfo['sub']));
    $history = [];
    $callbackUrl = identityProviderCallbackUrl($community->getShortCode(), $provider, $login, $userinfo, $idToken, 'fake-code', $history);

    $this->get($callbackUrl)->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    // history captures both calls the flow makes: the token exchange (index
    // 0, sent via Basic per discovery) and the userinfo fetch (index 1, the
    // id_token here carries no email - see validIdTokenClaims()).
    expect($history[0]['request']->getHeaderLine('Authorization'))->toStartWith('Basic ');

    $this->assertAuthenticatedAs($existingUser->fresh());
});

test('the configured scopes are requested, with openid always included', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->update(['scopes' => 'email profile groups']);

    fakeIdentityProviderHttp($provider->issuer);

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

    $this->get($callbackUrl)->assertStatus(400);

    $this->assertGuest();
});
