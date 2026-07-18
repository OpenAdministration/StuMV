<?php

use App\Livewire\Oidc\NewOidcClient;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new authorization-code client can be registered', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', "https://app.example.com/callback\nhttps://app.example.com/other-callback")
        ->set('scopes', ['openid', 'profile', 'email'])
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($client->redirect_uris)->toBe(['https://app.example.com/callback', 'https://app.example.com/other-callback'])
        ->and($client->scopes)->toBe(['openid', 'profile', 'email'])
        ->and($client->community_uid)->toBeNull()
        ->and($client->revoked)->toBeFalse();
});

test('blank lines in the redirect URIs field are ignored', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', "https://app.example.com/callback\n\n\n")
        ->set('scopes', ['openid'])
        ->call('save');

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->redirect_uris)->toBe(['https://app.example.com/callback']);
});

test('the plaintext secret is shown once after creation', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertSet('createdClientId', fn ($id) => filled($id))
        ->assertSet('createdClientSecret', fn ($secret) => filled($secret));
});

test('registering a client requires a name', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', '')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('registering a client requires at least one redirect URI', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', "\n\n")
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['redirectUris']);
});

test('registering a client rejects a redirect URI that is not a valid URL', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'not-a-url')
        ->set('scopes', ['openid'])
        ->call('save')
        ->assertHasErrors(['redirectUris']);
});

test('registering a client requires at least one scope', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('registering a client accepts the iban scope', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['iban'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->scopes)->toBe(['iban']);
});

test('registering a client accepts the address scope', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewOidcClient::class)
        ->set('name', 'My SSO App')
        ->set('redirectUris', 'https://app.example.com/callback')
        ->set('scopes', ['address'])
        ->call('save')
        ->assertHasNoErrors();

    $client = PassportClient::where('name', 'My SSO App')->firstOrFail();

    expect($client->scopes)->toBe(['address']);
});

test('a non-superadmin cannot register an OIDC client', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('oidc-clients.new'))->assertForbidden();
});
