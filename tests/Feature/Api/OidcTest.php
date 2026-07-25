<?php

use App\Ldap\Community;
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
        ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri', 'end_session_endpoint'])
        ->assertJsonFragment(['authorization_endpoint' => route('realm.passport.authorizations.authorize', ['realm' => $uid])])
        ->assertJsonFragment(['token_endpoint' => route('realm.passport.token', ['realm' => $uid])])
        ->assertJsonFragment(['userinfo_endpoint' => route('realm.openid.userinfo', ['realm' => $uid])])
        ->assertJsonFragment(['jwks_uri' => route('realm.openid.jwks', ['realm' => $uid])])
        ->assertJsonFragment(['end_session_endpoint' => route('realm.openid.end_session', ['realm' => $uid])])
        ->assertJsonFragment(['backchannel_logout_supported' => true])
        ->assertJsonFragment(['backchannel_logout_session_supported' => true])
        // client_secret_basic is deliberately not advertised - relies on
        // whatever's in front of PHP actually forwarding the Authorization
        // header, which is an easy, silent misconfiguration (see
        // RealmDiscoveryController's doc comment on this field). A
        // conformant client picks client_secret_post instead, which League
        // OAuth2 Server's AbstractGrant::getClientCredentials() already
        // reads from the request body regardless.
        ->assertJsonFragment(['token_endpoint_auth_methods_supported' => ['client_secret_post']]);
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
            'sub' => (string) $user->id,
            'email' => $user->email,
        ])
        ->assertJsonMissing(['given_name' => 'Jane'])
        ->assertJsonMissing(['phone_number' => '+49 123 456']);
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
