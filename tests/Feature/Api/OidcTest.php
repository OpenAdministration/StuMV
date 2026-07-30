<?php

use App\Ldap\Community;
use App\Models\PassportClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Tests\Support\TestLdap;

/**
 * Passport::actingAs() builds a transient AccessToken with no
 * oauth_access_token_id, so UserInfoController's `$request->user()->token()->scopes`
 * can't fall through to a real Token row and resolves to null - only an issue
 * for this faked-token testing shortcut, not real requests (whose bearer
 * token does carry a real access_token_id). This helper simulates a real,
 * DB-backed token instead, exercising the same path a genuine request takes.
 */
function actingWithRealAccessToken(User $user, string $communityUid, array $scopes): void
{
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $communityUid])->save();

    $token = Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => $scopes,
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $accessToken = new AccessToken([
        'oauth_access_token_id' => $token->id,
        'oauth_client_id' => $client->id,
        'oauth_user_id' => (string) $user->id,
        'oauth_scopes' => $scopes,
    ]);

    $user->withAccessToken($accessToken);
    resolve('auth')->guard('api')->setUser($user);
    resolve('auth')->shouldUse('api');
}

/**
 * jeremy379/laravel-openid-connect adds these on top of the existing Passport
 * setup - App\Entities\IdentityEntity supplies the claims (from the local
 * user row plus LDAP, same data the legacy SocialiteUser endpoint reads),
 * and the package's ClaimExtractor filters them down to what the token's
 * granted scopes permit. Every endpoint is realm-prefixed now (OIDC clients
 * are bound to the realm they were registered under).
 */
uses(RefreshDatabase::class);

test('the discovery document advertises the openid scopes and realm-prefixed endpoints', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->getJson("/$uid/.well-known/openid-configuration")
        ->assertOk()
        ->assertJsonFragment(['scopes_supported' => ['openid', 'profile', 'email', 'phone', 'address', 'groups']])
        ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri', 'end_session_endpoint', 'introspection_endpoint'])
        ->assertJsonFragment(['authorization_endpoint' => route('realm.passport.authorizations.authorize', ['realm' => $uid])])
        ->assertJsonFragment(['token_endpoint' => route('realm.passport.token', ['realm' => $uid])])
        ->assertJsonFragment(['userinfo_endpoint' => route('realm.openid.userinfo', ['realm' => $uid])])
        ->assertJsonFragment(['jwks_uri' => route('realm.openid.jwks', ['realm' => $uid])])
        ->assertJsonFragment(['end_session_endpoint' => route('realm.openid.end_session', ['realm' => $uid])])
        ->assertJsonFragment(['introspection_endpoint' => route('realm.openid.introspection', ['realm' => $uid])])
        ->assertJsonFragment(['backchannel_logout_supported' => true])
        ->assertJsonFragment(['backchannel_logout_session_supported' => true])
        // client_secret_basic is deliberately not advertised - relies on
        // whatever's in front of PHP actually forwarding the Authorization
        // header, which is an easy, silent misconfiguration (see
        // RealmDiscoveryController's doc comment on this field). A
        // conformant client picks client_secret_post instead, which League
        // OAuth2 Server's AbstractGrant::getClientCredentials() already
        // reads from the request body regardless.
        ->assertJsonFragment(['token_endpoint_auth_methods_supported' => ['client_secret_post']])
        ->assertJsonFragment(['introspection_endpoint_auth_methods_supported' => ['client_secret_post']]);
});

test('the global discovery/jwks endpoints no longer resolve', function (): void {
    $this->getJson('/.well-known/openid-configuration')->assertNotFound();
    $this->getJson('/oauth/jwks')->assertNotFound();
});

test('the jwks endpoint exposes the signing key', function (): void {
    $community = newCommunity();

    $response = $this->getJson('/'.$community->getShortCode().'/oauth/jwks')
        ->assertOk()
        ->assertJsonStructure(['keys' => [['kty', 'use', 'alg', 'n', 'e', 'kid']]]);

    // A relying party's JWT/JWKS library can require an exact 'kid' match to
    // select a signing key at all (e.g. Nextcloud user_oidc, built on
    // web-token/jwt-framework) - config('openid.token_headers.kid') has to
    // actually be set for OpenIDConnect\Laravel\JwksController to publish
    // one here, matching what id_tokens/logout_tokens are stamped with.
    expect($response->json('keys.0.kid'))->toBe(config('openid.token_headers.kid'))
        ->and($response->json('keys.0.kid'))->not->toBeEmpty();
});

test('the userinfo endpoint returns claims filtered by the granted scopes', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'givenName' => 'Jane',
        'sn' => 'Doe',
        'telephoneNumber' => '+49 123 456',
    ])->save();

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'email']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJson([
            'sub' => (string) $user->uid,
            'email' => $user->email,
        ])
        ->assertJsonMissing(['given_name' => 'Jane'])
        ->assertJsonMissing(['phone_number' => '+49 123 456']);
});

test('a token issued under one realm is rejected at another realm\'s userinfo endpoint', function (): void {
    // Regression: every access token is signed with the same server-wide
    // key regardless of realm, so 'auth:api' alone (which only checks the
    // token's own signature/expiry/revocation) isn't enough - the token's
    // owning client must also be checked against the {realm} in the URL.
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $user = TestLdap::member($community);

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'email']);

    $this->getJson('/'.$otherCommunity->getShortCode().'/oauth/userinfo')->assertForbidden();
});

test('granting the profile and phone scopes includes their claims', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'givenName' => 'Jane',
        'sn' => 'Doe',
        'telephoneNumber' => '+49 123 456',
    ])->save();

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'profile', 'phone']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJson([
            'given_name' => 'Jane',
            'family_name' => 'Doe',
            'phone_number' => '+49 123 456',
        ])
        ->assertJsonMissing(['email' => $user->email]);
});

test('granting the phone scope alone includes the phone_number claim', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'telephoneNumber' => '+49 123 456',
    ])->save();

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'phone']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJson(['phone_number' => '+49 123 456'])
        ->assertJsonMissing(['email' => $user->email]);
});

test('granting the address scope includes the address claim', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'street' => 'Hauptstraße 1',
        'postalCode' => '98693',
        'l' => 'Ilmenau',
    ])->save();

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'address']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJson([
            'address' => [
                'street_address' => 'Hauptstraße 1',
                'postal_code' => '98693',
                'locality' => 'Ilmenau',
            ],
        ])
        ->assertJsonMissing(['email' => $user->email]);
});

test('the address claim is omitted without the address scope', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'street' => 'Hauptstraße 1',
        'postalCode' => '98693',
        'l' => 'Ilmenau',
    ])->save();

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'email']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJsonMissingPath('address');
});

test('granting the groups scope includes the groups claim', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community, 'finance');
    TestLdap::attach($group, $user->ldap());

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'groups']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJson(['groups' => ['finance']]);
});

test('the groups claim is omitted without the groups scope', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community, 'finance');
    TestLdap::attach($group, $user->ldap());

    actingWithRealAccessToken($user, $community->getShortCode(), ['openid', 'email']);

    $this->getJson('/'.$community->getShortCode().'/oauth/userinfo')
        ->assertOk()
        ->assertJsonMissingPath('groups');
});

test('authorizing against a client bound to a different realm is rejected', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Other Realm SSO App', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $otherCommunity->getShortCode()])->save();

    $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $community->getShortCode(),
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]))->assertForbidden();
});

test('a client configured to skip consent completes the code flow without a prompt', function (): void {
    // requires_consent defaults to true (see the next test for that default
    // path) - this client explicitly opts out via
    // App\Models\PassportClient::skipsAuthorization(), so authorize redirects
    // straight back to the client with a code instead of showing the
    // consent screen.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://example.test/callback?code=');
});

test('the id_token\'s iss claim matches the discovery document\'s issuer exactly', function (): void {
    // Regression: App\Ldap\Community::issuerFor() is the single source of
    // truth for this across RealmDiscoveryController, IdTokenResponse and
    // BackChannelLogoutTokenBuilder - it used to just be the base package's
    // generic, non-realm-aware IssuedByGetter (scheme+host, no realm path
    // suffix, and not forced to https). A spec-compliant relying party (e.g.
    // Nextcloud's user_oidc) that validates iss against the discovery
    // document rejects the token outright on any mismatch, with nothing
    // logged on this end since nothing here ever throws.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Issuer Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    [, $payload] = explode('.', (string) $token->json('id_token'));
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    $discovery = $this->getJson("/$uid/.well-known/openid-configuration")->json();

    expect($claims['iss'])->toBe($discovery['issuer']);
});

test('the id_token\'s sub claim is the LDAP entryUUID, and matches the userinfo endpoint', function (): void {
    // Regression: sub must identify the physical LDAP entry (App\Models\User::$uid,
    // see App\Entities\IdentityEntity::setIdentifier()), not the local
    // user.id primary key, and both the id_token and /oauth/userinfo (App\Http\Controllers\Oidc\UserInfoController)
    // must agree on it.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Sub Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    [, $payload] = explode('.', (string) $token->json('id_token'));
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    $userinfo = $this->getJson("/$uid/oauth/userinfo", [
        'Authorization' => 'Bearer '.$token->json('access_token'),
    ])->json();

    expect($claims['sub'])->toBe((string) $user->uid)
        ->and($userinfo['sub'])->toBe((string) $user->uid);
});

test('the id_token\'s iat/exp claims are whole-second integers, not fractional-second floats', function (): void {
    // Regression: config('openid.use_microseconds') used to be true, which
    // made App\Services\Oidc\IdTokenResponse::getBuilder() stamp iat/exp
    // from a fractional-second DateTimeImmutable - lcobucci/jwt's default
    // ChainedFormatter (Encoding\MicrosecondBasedDateConversion) then
    // serializes that as a JSON float (e.g. 1737990000.123456) instead of a
    // plain integer, which some relying-party JWT libraries (e.g.
    // jumbojett/openid-connect-php) fail to validate at all.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Timestamp Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    [, $payload] = explode('.', (string) $token->json('id_token'));
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect($claims['iat'])->toBeInt()
        ->and($claims['exp'])->toBeInt();
});

test('a client left at its default configuration shows the consent prompt, and approving it completes the code flow', function (): void {
    // requires_consent defaults to true - every client shows the consent
    // screen (resources/views/auth/oauth/authorize.blade.php, registered via
    // Passport::authorizationView()) unless explicitly opted out, as in the
    // previous test.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Required Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()->assertSee($client->name);

    $approve = $this->post(route('realm.passport.authorizations.approve', ['realm' => $uid]), [
        'auth_token' => session('authToken'),
    ]);

    $approve->assertRedirect();
    expect($approve->headers->get('Location'))->toStartWith('https://example.test/callback?code=');
});

test('the consent screen hides the openid scope itself but still shows other requested scopes', function (): void {
    // "openid" carries no user-facing permission on its own (it's the base
    // scope every request needs, not a claim grant) - showing it as its own
    // "permission" line was just noise.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Scope Display Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid profile',
    ]));

    $response->assertOk()
        ->assertDontSee(__('auth.scope_openid'))
        ->assertSee(__('auth.scope_profile'));
});

test('the consent screen omits the permissions section entirely when only the openid scope is requested', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Openid Only Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertDontSee(__('auth.scope_openid'))
        ->assertDontSee(__('auth.authorize_permissions_notice'));
});

test('the consent screen shows the client\'s description, service provider and logo when set', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Branded Client', ['https://example.test/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'description' => 'A tool for managing student union finances.',
        'service_provider' => 'Student Union of Example University',
        'logo_id' => 'test-logo.svg',
    ])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertSee($client->name)
        ->assertSee('A tool for managing student union finances.')
        ->assertSee('Student Union of Example University')
        ->assertSee('oidc-client-logos/test-logo.svg', escape: false);
});

test('the consent screen shows the client\'s imprint, terms of service and privacy policy links when set', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Legal Links Client', ['https://example.test/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'imprint_url' => 'https://app.example.com/imprint',
        'terms_url' => 'https://app.example.com/terms',
        'privacy_policy_url' => 'https://app.example.com/privacy',
    ])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertSee('https://app.example.com/imprint', escape: false)
        ->assertSee('https://app.example.com/terms', escape: false)
        ->assertSee('https://app.example.com/privacy', escape: false)
        ->assertSee(__('oidc_clients.imprint_url'))
        ->assertSee(__('oidc_clients.terms_url'))
        ->assertSee(__('oidc_clients.privacy_policy_url'));
});

test('the consent screen omits the legal links block when none are set', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('No Legal Links Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertDontSee(__('oidc_clients.imprint_url'))
        ->assertDontSee(__('oidc_clients.terms_url'))
        ->assertDontSee(__('oidc_clients.privacy_policy_url'));
});

test('the consent screen omits description, service provider and logo when not set', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Plain Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertSee($client->name)
        ->assertDontSee('oidc-client-logos', escape: false);
});

test('a user who already holds a granted token covering the requested scopes is not re-prompted', function (): void {
    // App\Models\PassportClient::skipsAuthorization() mirrors Passport's own
    // AuthorizationController::hasGrantedScopes() - EditOidcClient::save()
    // revokes a client's tokens as soon as its scopes actually change (see
    // EditOidcClientTest), so a non-revoked token is exactly the right
    // signal that this user's consent still stands.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Required Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://example.test/callback?code=');
});

test('a user\'s consent is remembered even after their token has expired, as long as it was not revoked', function (): void {
    // Unlike Passport's own AuthorizationController::hasGrantedScopes()
    // (which only considers tokens still within their expiry window),
    // PassportClient::skipsAuthorization() deliberately ignores expires_at -
    // otherwise a user would be re-prompted every time their access token
    // merely expired, even though nothing about the grant itself changed.
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Required Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://example.test/callback?code=');
});

test('a broader scope request than previously granted still shows the prompt', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Required Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'scopes' => ['openid', 'profile']])->save();

    Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid profile',
    ]));

    $response->assertOk()->assertSee($client->name);
});

test('the oauth consent view points its approve/deny forms at this realm\'s own routes', function (): void {
    // Rendered directly here via an ad-hoc route (rather than driving the
    // full authorize flow, covered by the previous test) specifically to pin
    // the approve/deny route names independent of the skip/consent branch,
    // with its {realm} route parameter bound the same way a real
    // {realm}/oauth/* request would - so render it through an ad-hoc real
    // route rather than hand-building a Request/Route pair (route model
    // binding is otherwise easy to get subtly wrong in a way that doesn't
    // match production).
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent View Client', ['https://example.test/callback']);

    Route::middleware('web')->get('{realm}/_test-render-oauth-consent', fn (Community $realm, Request $request) => view('auth.oauth.authorize', [
        'client' => $client,
        'scopes' => [],
        'request' => $request,
        'authToken' => 'test-token',
    ]));

    $html = $this->get("/$uid/_test-render-oauth-consent")->assertOk()->getContent();

    // If the view still referenced the old, now-removed global route names
    // (passport.authorizations.approve/deny), rendering it would already
    // have thrown a RouteNotFoundException above.
    expect($html)->toContain(route('realm.passport.authorizations.approve', ['realm' => $uid]))
        ->toContain(route('realm.passport.authorizations.deny', ['realm' => $uid]));
});

/**
 * Runs a real authorization_code grant through /oauth/authorize + /oauth/token,
 * so IntrospectionController's ResourceServer::validateAuthenticatedRequest()
 * call has a genuinely signed access_token JWT to verify - unlike
 * actingWithRealAccessToken() above (which fakes the guard's user directly
 * and never produces a real bearer token), this exercises the exact string a
 * real client would present to /oauth/introspect.
 *
 * @return array{0: PassportClient, 1: string}
 */
function issueRealAccessToken(Community $community, User $user, string $scope = 'openid'): array
{
    $uid = $community->getShortCode();
    test()->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Introspection Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = test()->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => $scope,
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = test()->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    return [$client, (string) $token->json('access_token')];
}

/**
 * Same as issueRealAccessToken(), but also returns the refresh_token - kept
 * separate rather than changing that function's return shape and having to
 * touch every existing caller.
 *
 * @return array{0: PassportClient, 1: string, 2: string}
 */
function issueRealAccessTokenWithRefreshToken(Community $community, User $user, string $scope = 'openid'): array
{
    $uid = $community->getShortCode();
    test()->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Revocation Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = test()->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => $scope,
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = test()->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    return [$client, (string) $token->json('access_token'), (string) $token->json('refresh_token')];
}

test('introspecting a valid access token returns active=true with the expected claims', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user, 'openid profile');

    $response = $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ]);

    $response->assertOk()->assertJson([
        'active' => true,
        'client_id' => $client->id,
        'token_type' => 'Bearer',
    ]);

    expect($response->json('scope'))->toContain('openid')->toContain('profile')
        ->and($response->json('sub'))->toBe((string) $user->uid)
        ->and($response->json('exp'))->toBeInt()
        ->and($response->json('iat'))->toBeInt();
});

test('introspecting a revoked access token returns active=false', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    [, $payload] = explode('.', $accessToken);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    Token::whereKey($claims['jti'])->update(['revoked' => true]);

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ])->assertOk()->assertJson(['active' => false]);
});

test('introspecting a malformed token returns active=false rather than erroring', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Garbage Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => 'not-a-real-token',
    ])->assertOk()->assertJson(['active' => false]);
});

test('introspection requires a token parameter', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('No Token Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('introspection rejects a wrong client_secret', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => 'wrong-secret',
        'token' => $accessToken,
    ])->assertStatus(401)->assertJson(['error' => 'invalid_client']);
});

test('introspection rejects an unknown client_id', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => (string) Str::uuid(),
        'client_secret' => 'whatever',
        'token' => 'irrelevant',
    ])->assertStatus(401);
});

test('a client cannot introspect through a realm it is not bound to', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    $this->postJson(route('realm.openid.introspection', ['realm' => $otherCommunity->getShortCode()]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ])->assertForbidden();
});

test('introspecting a token whose own client belongs to a different realm returns active=false', function (): void {
    // Regression: unlike the previous test (the *calling* client is bound to
    // the wrong realm, caught by EnsureOidcClientMatchesRealm before the
    // controller even runs), this is the *introspected token's* client
    // being wrong - the caller itself is perfectly legitimate for its own
    // realm. Every access token is signed with the same server-wide key
    // regardless of realm, so without an explicit check a legitimate realm B
    // client could learn that a realm A token is active, its scopes, its
    // user, etc.
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $userInOtherRealm = TestLdap::member($otherCommunity);
    [, $tokenFromOtherRealm] = issueRealAccessToken($otherCommunity, $userInOtherRealm);

    $legitimateClient = resolve(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Legit Client In This Realm', ['https://example.test/callback']);
    $legitimateClient->forceFill(['community_uid' => $community->getShortCode()])->save();

    $this->postJson(route('realm.openid.introspection', ['realm' => $community->getShortCode()]), [
        'client_id' => $legitimateClient->id,
        'client_secret' => $legitimateClient->plainSecret,
        'token' => $tokenFromOtherRealm,
    ])->assertOk()->assertJson(['active' => false]);
});

test('introspection is rate-limited per client_id', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    $payload = [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ];

    for ($i = 0; $i < 60; $i++) {
        $this->postJson(route('realm.openid.introspection', ['realm' => $community->getShortCode()]), $payload)
            ->assertOk();
    }

    $this->postJson(route('realm.openid.introspection', ['realm' => $community->getShortCode()]), $payload)
        ->assertStatus(429);
});

test('the discovery document advertises the revocation endpoint', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->getJson("/$uid/.well-known/openid-configuration")
        ->assertOk()
        ->assertJsonFragment(['revocation_endpoint' => route('realm.openid.revocation', ['realm' => $uid])])
        ->assertJsonFragment(['revocation_endpoint_auth_methods_supported' => ['client_secret_post']]);
});

test('revoking an access token makes it fail introspection immediately', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ])->assertOk();

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ])->assertOk()->assertJson(['active' => false]);
});

test('revoking a refresh token also revokes its associated access token', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken, $refreshToken] = issueRealAccessTokenWithRefreshToken($community, $user);

    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $refreshToken,
        'token_type_hint' => 'refresh_token',
    ])->assertOk();

    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ])->assertOk()->assertJson(['active' => false]);

    // The refresh token itself must also be dead, not just the access token.
    $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'refresh_token' => $refreshToken,
    ])->assertStatus(400); // League OAuth2 Server's invalid_grant, not 401
});

test('revocation ignores a missing token_type_hint and still finds the right token type', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, , $refreshToken] = issueRealAccessTokenWithRefreshToken($community, $user);

    // No token_type_hint at all, and the token is a refresh token, not an
    // access token - RFC 7009 §2.1 requires the server to still try the
    // other type rather than giving up.
    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $refreshToken,
    ])->assertOk();

    $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'refresh_token',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'refresh_token' => $refreshToken,
    ])->assertStatus(400); // League OAuth2 Server's invalid_grant, not 401
});

test('revoking a token that belongs to a different client still returns 200, per RFC 7009', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [, $accessToken] = issueRealAccessToken($community, $user);

    $otherClient = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Other Client', ['https://example.test/callback']);
    $otherClient->forceFill(['community_uid' => $uid])->save();

    // Must not leak whether the token exists/belongs to someone else - the
    // response is identical either way, and (checked below) the token is
    // actually untouched: RevocationController::revokeAccessToken() checks
    // ownership and silently no-ops instead of revoking it.
    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $otherClient->id,
        'client_secret' => $otherClient->plainSecret,
        'token' => $accessToken,
    ])->assertOk();

    // Introspection itself doesn't require the caller to *own* the token
    // (any authenticated same-realm client may check any realm token, the
    // usual resource-server pattern) - still active proves the revoke call
    // above was a no-op, not that introspection is scoped per-client too.
    $this->postJson(route('realm.openid.introspection', ['realm' => $uid]), [
        'client_id' => $otherClient->id,
        'client_secret' => $otherClient->plainSecret,
        'token' => $accessToken,
    ])->assertOk()->assertJson(['active' => true]);
});

test('revoking an unknown/garbage token still returns 200, per RFC 7009', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Garbage Revoke Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => 'garbage-token-value',
    ])->assertOk();
});

test('revocation requires a token parameter', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('No Token Revoke Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('revocation rejects a wrong client_secret', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessToken($community, $user);

    $this->postJson(route('realm.openid.revocation', ['realm' => $uid]), [
        'client_id' => $client->id,
        'client_secret' => 'wrong-secret',
        'token' => $accessToken,
    ])->assertStatus(401)->assertJson(['error' => 'invalid_client']);
});
