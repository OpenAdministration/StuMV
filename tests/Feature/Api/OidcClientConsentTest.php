<?php

use App\Livewire\Oidc\EditOidcClient;
use App\Models\OidcClientConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * App\Models\PassportClient::skipsAuthorization() remembers a user's consent
 * independently of any token's own lifetime (Passport's stock
 * AuthorizationController::hasGrantedScopes() only looks at currently-active
 * tokens, which would force a fresh prompt on every access-token expiry even
 * though nothing about the grant changed) - see App\Listeners\RecordOidcClientConsent
 * and the oauth_client_consents migration.
 */
function grantAccessToken(User $user, string $clientId, array $scopes, bool $revoked = false): Token
{
    $token = Token::create([
        'id' => \Illuminate\Support\Str::random(80),
        'user_id' => $user->id,
        'client_id' => $clientId,
        'scopes' => $scopes,
        'revoked' => $revoked,
        'expires_at' => now()->addHour(),
    ]);

    event(new AccessTokenCreated($token->id, $user->id, $clientId));

    return $token;
}

test('issuing an access token records standing consent for its scopes', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    grantAccessToken($user, $client->id, ['openid', 'profile']);

    $consent = OidcClientConsent::where('client_id', $client->id)->where('user_id', $user->id)->firstOrFail();

    expect($consent->scopes)->toBe(['openid', 'profile']);
});

test('a user with standing consent is not re-prompted even without a currently active token', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    // The token that originally earned this consent is already revoked (e.g.
    // it simply expired and nothing else happened) - Passport's own
    // active-token fallback can't see this as a reason to skip, but the
    // standing consent record still can.
    grantAccessToken($user, $client->id, ['openid'], revoked: true);

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

test('a broader scope request than previously consented to still shows the prompt', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Test SSO Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'scopes' => ['openid', 'profile']])->save();

    grantAccessToken($user, $client->id, ['openid']);

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid profile',
    ]));

    $response->assertOk()->assertSee($client->name);
});

test('changing a client\'s scopes clears standing consent for it', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid', 'profile']])->save();
    $client->refresh();

    $user = TestLdap::member($community);
    grantAccessToken($user, $client->id, ['openid', 'profile']);

    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', ['openid', 'profile', 'email'])
        ->call('save');

    expect(OidcClientConsent::where('client_id', $client->id)->exists())->toBeFalse();
});
