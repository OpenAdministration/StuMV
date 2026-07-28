<?php

use App\Livewire\Oidc\NewOidcClient;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new authorization-code client can be registered', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', "https://app.example.com/callback\nhttps://app.example.com/other-callback")
        ->set('scopes', ['openid', 'profile', 'email'])
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($client->redirect_uris)->toBe(['https://app.example.com/callback', 'https://app.example.com/other-callback'])
        ->and($client->scopes)->toBe(['openid', 'profile', 'email'])
        ->and($client->community_uid)->toBe($community->getShortCode())
        ->and($client->revoked)->toBeFalse();
});

test('blank lines in the redirect URIs field are ignored', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', "https://app.example.com/callback\n\n\n")
        ->set('scopes', ['openid'])
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->redirect_uris)->toBe(['https://app.example.com/callback']);
});

test('the plaintext secret is shown once after creation', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertSet('createdClientId', fn ($id) => filled($id))
        ->assertSet('createdClientSecret', fn ($secret) => filled($secret));
});

test('registering a client requires a name', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', '')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('registering a client requires at least one redirect URI', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', "\n\n")
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['redirectUris']);
});

test('registering a client rejects a redirect URI that is not a valid URL', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'not-a-url')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['redirectUris']);
});

test('registering a client rejects a redirect URI containing a wildcard', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/*')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['redirectUris']);
});

test('registering a client accepts a post-logout redirect URI containing a wildcard', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('postLogoutRedirectUris', 'https://app.example.com/*')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->post_logout_redirect_uris)->toBe(['https://app.example.com/*']);
});

test('registering a client requires at least one scope', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('registering a client rejects the iban scope', function (): void {
    // No IBAN data is stored anywhere in this app (LDAP or DB) - the scope
    // was removed rather than ever exposing a permanently-null claim.
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['iban'])
        ->call('save')
        ->assertHasErrors(['scopes.0']);
});

test('registering a client accepts the address scope', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['address'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->scopes)->toBe(['address']);
});

test('a new client defaults to requiring authorization consent', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->requires_consent)->toBeTrue();
});

test('a client can be registered to skip the authorization prompt', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->set('requiresConsent', false)
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->requires_consent)->toBeFalse();
});

test('a client can be registered with a back-channel logout URI', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->set('backChannelLogoutUri', 'https://app.example.com/backchannel-logout')
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->back_channel_logout_uri)->toBe('https://app.example.com/backchannel-logout');
});

test('the back-channel logout URI is optional', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->back_channel_logout_uri)->toBeNull();
});

test('registering a client rejects a back-channel logout URI that is not a valid URL', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->set('backChannelLogoutUri', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['backChannelLogoutUri']);
});

test('a client can be registered with a description, service provider and logo', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('description', 'A tool for managing student union finances.')
        ->set('serviceProvider', 'Student Union of Example University')
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->description)->toBe('A tool for managing student union finances.')
        ->and($client->service_provider)->toBe('Student Union of Example University')
        ->and($client->logo_id)->not->toBeNull();

    Storage::disk('public')->assertExists('oidc-client-logos/'.$client->logo_id);
});

test('description, service provider and logo are optional', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->description)->toBeNull()
        ->and($client->service_provider)->toBeNull()
        ->and($client->logo_id)->toBeNull();
});

test('an invalid logo file type is rejected and never reaches storage', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewOidcClient::class, ['realm' => $community])
        ->set('name', 'My SSO App')
        ->set('logo', UploadedFile::fake()->create('not-an-image.pdf', 10))
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['logo']);

    expect(PassportClient::where('name', 'My SSO App')->exists())->toBeFalse();
    Storage::disk('public')->assertDirectoryEmpty('oidc-client-logos');
});

test('a non-admin cannot register an OIDC client', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.oidc-clients.new', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no OIDC clients feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.oidc-clients.new', ['realm' => 'admin']))->assertNotFound();
});
