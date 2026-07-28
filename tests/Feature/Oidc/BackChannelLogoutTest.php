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
use Lcobucci\JWT\Validation\Constraint\SignedWith;

uses(RefreshDatabase::class);

function grantOidcToken(string $clientId, User $user): Token
{
    return Token::create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $clientId,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
}

test('logging out sends a signed back-channel logout notification to a client the user has an active token for', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'back_channel_logout_uri' => 'https://app.example.com/backchannel-logout',
    ])->save();
    $grantedToken = grantOidcToken($client->id, $user);

    $this->post(route('realm.logout', ['realm' => $uid]))->assertRedirect();

    Http::assertSent(function ($request) use ($client, $user, $uid, $grantedToken) {
        if ($request->url() !== 'https://app.example.com/backchannel-logout') {
            return false;
        }

        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::file(Passport::keyPath('oauth-private.key')),
            InMemory::file(Passport::keyPath('oauth-public.key')),
        );

        $token = $config->parser()->parse($request['logout_token']);
        expect($config->validator()->validate($token, new SignedWith($config->signer(), $config->verificationKey())))->toBeTrue();

        // Matches the 'kid' the JWKS endpoint publishes (see
        // App\Services\Oidc\BackChannelLogoutTokenBuilder) - a relying party
        // that requires an exact 'kid' match to select a signing key can't
        // verify this token's signature without it.
        expect($token->headers()->get('kid'))->toBe(config('openid.token_headers.kid'));

        $claims = $token->claims();
        expect((string) $claims->get('iss'))->toBe(rtrim(url('/'.$uid), '/'))
            ->and($claims->get('aud'))->toBe([$client->id])
            ->and($claims->get('sub'))->toBe((string) $user->uid)
            ->and($claims->get('sid'))->toBe($grantedToken->id)
            ->and($claims->has('nonce'))->toBeFalse()
            ->and((array) $claims->get('events'))->toHaveKey('http://schemas.openid.net/event/backchannel-logout');

        return true;
    });
});

test('a user with two live tokens for the same client gets one logout notification per token, each with its own sid', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'back_channel_logout_uri' => 'https://app.example.com/backchannel-logout',
    ])->save();
    $firstToken = grantOidcToken($client->id, $user);
    $secondToken = grantOidcToken($client->id, $user);

    $this->post(route('realm.logout', ['realm' => $uid]))->assertRedirect();

    $sidsSent = [];
    Http::assertSentCount(2);
    Http::assertSent(function ($request) use (&$sidsSent) {
        if ($request->url() !== 'https://app.example.com/backchannel-logout') {
            return false;
        }

        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::file(Passport::keyPath('oauth-private.key')),
            InMemory::file(Passport::keyPath('oauth-public.key')),
        );

        $sidsSent[] = (string) $config->parser()->parse($request['logout_token'])->claims()->get('sid');

        return true;
    });

    expect($sidsSent)->toEqualCanonicalizing([$firstToken->id, $secondToken->id]);
});

test('a client without a configured back-channel logout URI is not notified', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $uid])->save();
    grantOidcToken($client->id, $user);

    $this->post(route('realm.logout', ['realm' => $uid]))->assertRedirect();

    Http::assertNothingSent();
});

test('a client the user only holds a revoked token for is not notified', function (): void {
    Http::fake();

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'back_channel_logout_uri' => 'https://app.example.com/backchannel-logout',
    ])->save();
    grantOidcToken($client->id, $user)->update(['revoked' => true]);

    $this->post(route('realm.logout', ['realm' => $uid]))->assertRedirect();

    Http::assertNothingSent();
});

test('logout still succeeds even if a client\'s back-channel logout endpoint fails', function (): void {
    Http::fake(['app.example.com/*' => Http::response('', 500)]);

    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = actingAsMember($community);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $uid,
        'back_channel_logout_uri' => 'https://app.example.com/backchannel-logout',
    ])->save();
    grantOidcToken($client->id, $user);

    $this->post(route('realm.logout', ['realm' => $uid]))->assertRedirect();

    $this->assertGuest();
});
