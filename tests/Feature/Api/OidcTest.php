<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
function actingWithRealAccessToken(User $user, array $scopes): void
{
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);

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
    app('auth')->guard('api')->setUser($user);
    app('auth')->shouldUse('api');
}

/**
 * jeremy379/laravel-openid-connect adds these on top of the existing Passport
 * setup - App\Entities\IdentityEntity supplies the claims (from the local
 * user row plus LDAP, same data the legacy SocialiteUser endpoint reads),
 * and the package's ClaimExtractor filters them down to what the token's
 * granted scopes permit.
 */
uses(RefreshDatabase::class);

test('the discovery document advertises the openid scopes and endpoints', function (): void {
    $this->getJson('/.well-known/openid-configuration')
        ->assertOk()
        ->assertJsonFragment(['scopes_supported' => ['openid', 'profile', 'email', 'phone', 'address']])
        ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri']);
});

test('the jwks endpoint exposes the signing key', function (): void {
    $this->getJson('/oauth/jwks')
        ->assertOk()
        ->assertJsonStructure(['keys' => [['kty', 'use', 'alg', 'n', 'e']]]);
});

test('the userinfo endpoint returns claims filtered by the granted scopes', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $user->ldap()->fill([
        'givenName' => 'Jane',
        'sn' => 'Doe',
        'telephoneNumber' => '+49 123 456',
    ])->save();

    actingWithRealAccessToken($user, ['openid', 'email']);

    $this->getJson('/oauth/userinfo')
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

    actingWithRealAccessToken($user, ['openid', 'profile', 'phone']);

    $this->getJson('/oauth/userinfo')
        ->assertOk()
        ->assertJson([
            'given_name' => 'Jane',
            'family_name' => 'Doe',
            'phone_number' => '+49 123 456',
        ])
        ->assertJsonMissing(['email' => $user->email]);
});
