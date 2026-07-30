<?php

use App\Models\IdentityProviderSession;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Generates a fresh RSA keypair and the matching JWKS document, so a test can
 * sign a logout_token the same way a real identity provider would and have
 * OidcLoginController::backChannelLogout() verify it against that JWKS -
 * mirrors fakeIdentityProvider()'s role for the login flow in
 * IdentityProviderLoginTest.php, just for the reverse (inbound) direction.
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

/** Fakes the discovery document (including jwks_uri) and the JWKS endpoint itself. */
function fakeIdentityProviderJwks(string $issuer, array $jwks): void
{
    Http::fake([
        $issuer.'/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => $issuer.'/authorize',
            'token_endpoint' => $issuer.'/token',
            'userinfo_endpoint' => $issuer.'/userinfo',
            'jwks_uri' => $issuer.'/jwks',
        ]),
        $issuer.'/jwks' => Http::response($jwks),
    ]);
}

function makeSignedLogoutToken(string $privateKeyPem, array $claims, string $kid = 'test-key'): string
{
    return JWT::encode($claims, $privateKeyPem, 'RS256', $kid);
}

function validLogoutTokenClaims(string $issuer, string $clientId, string $sub): array
{
    return [
        'iss' => $issuer,
        'aud' => $clientId,
        'sub' => $sub,
        'iat' => time(),
        'jti' => bin2hex(random_bytes(8)),
        'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []],
    ];
}

test('a valid logout_token destroys the matching session and clears the correlation record', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $sessionId = 'test-session-'.bin2hex(random_bytes(8));
    session()->getHandler()->write($sessionId, serialize(['foo' => 'bar']));

    IdentityProviderSession::create([
        'provider_id' => $provider->id,
        'external_sub' => 'external-123',
        'session_id' => $sessionId,
    ]);

    $logoutToken = makeSignedLogoutToken($privateKey, validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123'));

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($sessionId))->toBe('')
        ->and(IdentityProviderSession::where('provider_id', $provider->id)->where('external_sub', 'external-123')->count())->toBe(0);
});

test('a logout_token destroys every session recorded for that sub, not just one', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $sessionIdA = 'test-session-a-'.bin2hex(random_bytes(8));
    $sessionIdB = 'test-session-b-'.bin2hex(random_bytes(8));
    session()->getHandler()->write($sessionIdA, serialize(['device' => 'phone']));
    session()->getHandler()->write($sessionIdB, serialize(['device' => 'laptop']));

    IdentityProviderSession::create(['provider_id' => $provider->id, 'external_sub' => 'external-123', 'session_id' => $sessionIdA]);
    IdentityProviderSession::create(['provider_id' => $provider->id, 'external_sub' => 'external-123', 'session_id' => $sessionIdB]);

    $logoutToken = makeSignedLogoutToken($privateKey, validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123'));

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($sessionIdA))->toBe('')
        ->and(session()->getHandler()->read($sessionIdB))->toBe('');
});

test('a logout_token for a different sub does not touch an unrelated session', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $sessionId = 'test-session-'.bin2hex(random_bytes(8));
    session()->getHandler()->write($sessionId, serialize(['foo' => 'bar']));
    IdentityProviderSession::create(['provider_id' => $provider->id, 'external_sub' => 'someone-else', 'session_id' => $sessionId]);

    $logoutToken = makeSignedLogoutToken($privateKey, validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123'));

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($sessionId))->not->toBe('');
});

test('a missing logout_token is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [])
        ->assertStatus(400)
        ->assertJson(['error' => 'invalid_request']);
});

test('a logout_token signed by the wrong key is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [, $jwks] = makeRsaKeyPairAndJwks();
    [$otherPrivateKey] = makeRsaKeyPairAndJwks('other-key');
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $logoutToken = makeSignedLogoutToken($otherPrivateKey, validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123'), 'other-key');

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a logout_token with the wrong issuer is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $claims = validLogoutTokenClaims('https://not-the-issuer.test', $provider->client_id, 'external-123');
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a logout_token with the wrong audience is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $claims = validLogoutTokenClaims($provider->issuer, 'someone-elses-client-id', 'external-123');
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a logout_token without the backchannel-logout event is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    unset($claims['events']);
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a logout_token containing a nonce claim is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    $claims['nonce'] = 'should-not-be-here';
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a logout_token without a sub claim is rejected', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    unset($claims['sub']);
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('a disabled identity provider rejects back-channel logout', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), enabled: false);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => 'irrelevant',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});

test('another realm\'s identity provider cannot be used to back-channel logout through this realm', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeIdentityProvider($otherCommunity->getShortCode());

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => 'irrelevant',
    ])->assertStatus(400)->assertJson(['error' => 'invalid_request']);
});
