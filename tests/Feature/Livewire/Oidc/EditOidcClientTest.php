<?php

use App\Livewire\Oidc\EditOidcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function grantToken(string $clientId, string $userId, array $scopes): Token
{
    return Token::create([
        'id' => Str::random(80),
        'user_id' => $userId,
        'client_id' => $clientId,
        'scopes' => $scopes,
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
}

test('the edit form is pre-filled with the client\'s current values', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid', 'profile']])->save();
    // requires_consent is only set by a DB-level default (see the migration),
    // never by ClientRepository::create() - refresh() is needed so this
    // in-memory instance actually reflects it, matching what a real request
    // gets via route-model binding.
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertSet('name', 'My SSO App')
        ->assertSet('redirectUris', 'https://app.example.com/callback')
        ->assertSet('scopes', ['openid', 'profile']);
});

test('a client\'s name, redirect URIs and scopes can be updated', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('name', 'My Renamed App')
        ->set('redirectUris', "https://app.example.com/callback\nhttps://app.example.com/second-callback")
        ->set('scopes', ['openid', 'email'])
        ->call('save');

    $client->refresh();

    expect($client->name)->toBe('My Renamed App')
        ->and($client->redirect_uris)->toBe(['https://app.example.com/callback', 'https://app.example.com/second-callback'])
        ->and($client->scopes)->toBe(['openid', 'email']);
});

test('the edit form is pre-filled with the client\'s requires-consent setting', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid'], 'requires_consent' => false])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertSet('requiresConsent', false);
});

test('a client\'s requires-consent setting can be updated', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('requiresConsent', false)
        ->call('save');

    $client->refresh();

    expect($client->requires_consent)->toBeFalse();
});

test('changing a client\'s scopes revokes its existing tokens, so users must re-consent', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid', 'profile']])->save();
    $client->refresh();
    $token = grantToken($client->id, TestLdap::member($community)->id, ['openid', 'profile']);
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', ['openid', 'profile', 'email'])
        ->call('save');

    expect($token->fresh()->revoked)->toBeTrue();
});

test('reordering a client\'s scopes without actually changing the set does not revoke its tokens', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid', 'profile']])->save();
    $client->refresh();
    $token = grantToken($client->id, TestLdap::member($community)->id, ['openid', 'profile']);
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', ['profile', 'openid'])
        ->call('save');

    expect($token->fresh()->revoked)->toBeFalse();
});

test('editing a client without changing its scopes leaves its existing tokens untouched', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid', 'profile']])->save();
    $client->refresh();
    $token = grantToken($client->id, TestLdap::member($community)->id, ['openid', 'profile']);
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('name', 'Renamed App')
        ->call('save');

    expect($token->fresh()->revoked)->toBeFalse();
});

test('editing a client requires at least one scope', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('the edit form is pre-filled with the client\'s back-channel logout URI', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $community->getShortCode(),
        'scopes' => ['openid'],
        'back_channel_logout_uri' => 'https://app.example.com/backchannel-logout',
    ])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertSet('backChannelLogoutUri', 'https://app.example.com/backchannel-logout');
});

test('a client\'s back-channel logout URI can be set and cleared', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('backChannelLogoutUri', 'https://app.example.com/backchannel-logout')
        ->call('save');

    expect($client->fresh()->back_channel_logout_uri)->toBe('https://app.example.com/backchannel-logout');

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('backChannelLogoutUri', '')
        ->call('save');

    expect($client->fresh()->back_channel_logout_uri)->toBeNull();
});

test('the edit form is pre-filled with the client\'s description, service provider and logo', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill([
        'community_uid' => $community->getShortCode(),
        'description' => 'A tool for managing student union finances.',
        'service_provider' => 'Student Union of Example University',
        'logo_id' => 'existing-logo.webp',
    ])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertSet('description', 'A tool for managing student union finances.')
        ->assertSet('serviceProvider', 'Student Union of Example University')
        ->assertSet('logoId', 'existing-logo.webp');
});

test('a client\'s description and service provider can be updated', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('description', 'Updated description.')
        ->set('serviceProvider', 'Updated Provider')
        ->call('save')
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->description)->toBe('Updated description.')
        ->and($client->service_provider)->toBe('Updated Provider');
});

test('an admin can upload a logo for an existing client', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->logo_id)->not->toBeNull();
    Storage::disk('public')->assertExists('oidc-client-logos/'.$client->logo_id);
});

test('uploading a new logo replaces and deletes the old one', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('oidc-client-logos/old-logo.webp', 'fake-bytes');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid'], 'logo_id' => 'old-logo.webp'])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->logo_id)->not->toBe('old-logo.webp');
    Storage::disk('public')->assertMissing('oidc-client-logos/old-logo.webp');
    Storage::disk('public')->assertExists('oidc-client-logos/'.$client->logo_id);
});

test('a client\'s logo can be removed', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('oidc-client-logos/existing-logo.webp', 'fake-bytes');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'logo_id' => 'existing-logo.webp'])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->call('removeLogo')
        ->assertSet('logoId', null);

    expect($client->fresh()->logo_id)->toBeNull();
    Storage::disk('public')->assertMissing('oidc-client-logos/existing-logo.webp');
});

test('an invalid logo file type is rejected and never reaches storage', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('logo', UploadedFile::fake()->create('not-an-image.pdf', 10))
        ->call('save')
        ->assertHasErrors(['logo']);

    expect($client->fresh()->logo_id)->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('oidc-client-logos');
});

test('a directory API client cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Directory API Client');
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertStatus(404);
});

test('another realm\'s OIDC client cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Other Realm SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $otherCommunity->getShortCode()])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->assertStatus(404);
});

test('a non-admin cannot edit an OIDC client', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsModerator($community);

    $this->get(route('realms.oidc-clients.edit', ['realm' => $community->getShortCode(), 'client' => $client->id]))->assertForbidden();
});
