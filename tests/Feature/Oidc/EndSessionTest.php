<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

uses(RefreshDatabase::class);

/**
 * A real, signed id_token_hint - same key pair EndSessionController verifies
 * against (Passport's own oauth-private/public.key, see
 * App\Services\Oidc\BackChannelLogoutTokenBuilder for the equivalent
 * logout_token construction).
 */
function makeIdTokenHint(string $clientId, User $user): string
{
    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::file(Passport::keyPath('oauth-private.key')),
        InMemory::file(Passport::keyPath('oauth-public.key')),
    );

    return $config->builder()
        ->permittedFor($clientId)
        ->relatedTo((string) $user->id)
        ->getToken($config->signer(), $config->signingKey())
        ->toString();
}

test('a valid id_token_hint and registered post_logout_redirect_uri end the session and redirect back with state', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'post_logout_redirect_uris' => ['https://app.example.com/logged-out'],
    ])->save();

    $response = $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'id_token_hint' => makeIdTokenHint($client->id, $user),
        'post_logout_redirect_uri' => 'https://app.example.com/logged-out',
        'state' => 'xyz123',
    ]));

    $response->assertRedirect('https://app.example.com/logged-out?state=xyz123');
    $this->assertGuest();
});

test('a post_logout_redirect_uri the client never registered is never honored', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'post_logout_redirect_uris' => ['https://app.example.com/logged-out'],
    ])->save();

    $response = $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'id_token_hint' => makeIdTokenHint($client->id, $user),
        'post_logout_redirect_uri' => 'https://evil.example.com/steal-me',
    ]));

    $response->assertRedirect(route('realm.login', ['realm' => $uid]));
    $this->assertGuest();
});

test('a client_id param alone (no id_token_hint) also resolves the client', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'post_logout_redirect_uris' => ['https://app.example.com/logged-out'],
    ])->save();

    $response = $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'client_id' => $client->id,
        'post_logout_redirect_uri' => 'https://app.example.com/logged-out',
    ]));

    $response->assertRedirect('https://app.example.com/logged-out');
    $this->assertGuest();
});

test('a guest with no active session hitting end_session still redirects to a validated uri without error', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'post_logout_redirect_uris' => ['https://app.example.com/logged-out'],
    ])->save();

    $response = $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'client_id' => $client->id,
        'post_logout_redirect_uri' => 'https://app.example.com/logged-out',
    ]));

    $response->assertRedirect('https://app.example.com/logged-out');
    $this->assertGuest();
});

test('no post_logout_redirect_uri at all falls back to the realm login page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realm.openid.end_session', ['realm' => $uid]));

    $response->assertRedirect(route('realm.login', ['realm' => $uid]));
    $this->assertGuest();
});

test('a malformed id_token_hint is ignored rather than erroring', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'id_token_hint' => 'not-a-real-jwt',
    ]));

    $response->assertRedirect(route('realm.login', ['realm' => $uid]));
    $this->assertGuest();
});

test('ending a session via end_session also notifies other clients via back-channel logout', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $rpClient = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $rpClient->forceFill(['community_uid' => $uid])->save();

    $otherClient = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Other App', ['https://other.example.com/callback']);
    $otherClient->forceFill([
        'community_uid' => $uid,
        'back_channel_logout_uri' => 'https://other.example.com/backchannel-logout',
    ])->save();
    Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $otherClient->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $this->get(route('realm.openid.end_session', [
        'realm' => $uid,
        'id_token_hint' => makeIdTokenHint($rpClient->id, $user),
    ]))->assertRedirect(route('realm.login', ['realm' => $uid]));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://other.example.com/backchannel-logout');
});
