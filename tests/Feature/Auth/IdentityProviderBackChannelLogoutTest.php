<?php

use App\Models\IdentityProviderSession;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    writeTestSession($sessionIdA, ['device' => 'phone']);
    writeTestSession($sessionIdB, ['device' => 'laptop']);

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

test('a logout_token with neither a sub nor a sid claim is rejected', function (): void {
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

test('a logout_token identifying the session by sid alone ends exactly that session', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $sessionId = 'test-session-'.bin2hex(random_bytes(8));
    session()->getHandler()->write($sessionId, serialize(['foo' => 'bar']));

    IdentityProviderSession::create([
        'provider_id' => $provider->id,
        'external_sub' => 'external-123',
        'external_sid' => 'session-abc',
        'session_id' => $sessionId,
    ]);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    unset($claims['sub']);
    $claims['sid'] = 'session-abc';
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($sessionId))->toBe('')
        ->and(IdentityProviderSession::where('external_sid', 'session-abc')->count())->toBe(0);
});

test('a logout_token carrying a sid leaves the same user\'s other sessions signed in', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $endedSessionId = 'test-session-ended-'.bin2hex(random_bytes(8));
    $otherSessionId = 'test-session-other-'.bin2hex(random_bytes(8));
    writeTestSession($endedSessionId, ['device' => 'phone']);
    writeTestSession($otherSessionId, ['device' => 'laptop']);

    IdentityProviderSession::create(['provider_id' => $provider->id, 'external_sub' => 'external-123', 'external_sid' => 'session-phone', 'session_id' => $endedSessionId]);
    IdentityProviderSession::create(['provider_id' => $provider->id, 'external_sub' => 'external-123', 'external_sid' => 'session-laptop', 'session_id' => $otherSessionId]);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    $claims['sid'] = 'session-phone';
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($endedSessionId))->toBe('')
        ->and(session()->getHandler()->read($otherSessionId))->not->toBe('')
        ->and(IdentityProviderSession::where('external_sid', 'session-laptop')->count())->toBe(1);
});

test('a logout_token carrying a sid still ends sessions recorded before the provider sent one', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    [$privateKey, $jwks] = makeRsaKeyPairAndJwks();
    fakeIdentityProviderJwks($provider->issuer, $jwks);

    $legacySessionId = 'test-session-legacy-'.bin2hex(random_bytes(8));
    session()->getHandler()->write($legacySessionId, serialize(['foo' => 'bar']));

    IdentityProviderSession::create([
        'provider_id' => $provider->id,
        'external_sub' => 'external-123',
        'session_id' => $legacySessionId,
    ]);

    $claims = validLogoutTokenClaims($provider->issuer, $provider->client_id, 'external-123');
    $claims['sid'] = 'session-abc';
    $logoutToken = makeSignedLogoutToken($privateKey, $claims);

    $this->postJson(route('identity-provider.backchannel-logout', ['realm' => $community->getShortCode(), 'provider' => $provider->id]), [
        'logout_token' => $logoutToken,
    ])->assertStatus(200);

    expect(session()->getHandler()->read($legacySessionId))->toBe('');
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
