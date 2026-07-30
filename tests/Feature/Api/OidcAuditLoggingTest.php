<?php

use App\Ldap\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Tests\Support\TestLdap;

/**
 * These previously left no trace at all in storage/logs/laravel.log: failed
 * OIDC client authentication attempts, consent approve/deny decisions, token
 * issuance, and introspection/revocation calls. Log::spy() (a plain
 * Mockery-backed facade spy, no extra test package needed) lets these
 * assert a matching Log:: call happened without changing what gets logged.
 */
uses(RefreshDatabase::class);

function issueRealAccessTokenForLogging(Community $community, User $user, string $scope = 'openid'): array
{
    $uid = $community->getShortCode();
    test()->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Audit Log Test Client', ['https://example.test/callback']);
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

test('issuing an access token is logged', function (): void {
    Log::spy();

    $community = newCommunity();
    $user = TestLdap::member($community);
    issueRealAccessTokenForLogging($community, $user);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'OIDC access token issued')
        ->atLeast()->once();
});

test('an unknown client_id at the token endpoint is logged', function (): void {
    Log::spy();

    $community = newCommunity();

    $this->post(route('realm.passport.token', ['realm' => $community->getShortCode()]), [
        'grant_type' => 'client_credentials',
        'client_id' => (string) Str::uuid(),
        'client_secret' => 'whatever',
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'unknown client_id'))
        ->atLeast()->once();
});

test('a cross-realm OIDC request is logged', function (): void {
    Log::spy();

    $community = newCommunity();
    $otherCommunity = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Wrong Realm Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $otherCommunity->getShortCode()])->save();

    $this->postJson(route('realm.openid.introspection', ['realm' => $community->getShortCode()]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => 'irrelevant',
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'different realm'))
        ->atLeast()->once();
});

test('a successful introspection call is logged', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessTokenForLogging($community, $user);

    Log::spy();

    $this->postJson(route('realm.openid.introspection', ['realm' => $community->getShortCode()]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'OIDC introspection: token active')
        ->once();
});

test('a revocation call is logged', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    [$client, $accessToken] = issueRealAccessTokenForLogging($community, $user);

    Log::spy();

    $this->postJson(route('realm.openid.revocation', ['realm' => $community->getShortCode()]), [
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'token' => $accessToken,
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'OIDC revocation processed' && $context['revoked'] === true)
        ->once();
});

test('approving consent is logged', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Log Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]))->assertOk();

    Log::spy();

    $this->post(route('realm.passport.authorizations.approve', ['realm' => $uid]), [
        'auth_token' => session('authToken'),
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'OIDC consent approved' && $context['client_id'] === $client->id)
        ->once();
});

test('denying consent is logged', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Consent Deny Log Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]))->assertOk();

    Log::spy();

    $this->delete(route('realm.passport.authorizations.deny', ['realm' => $uid]), [
        'auth_token' => session('authToken'),
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'OIDC consent denied' && $context['client_id'] === $client->id)
        ->once();
});
