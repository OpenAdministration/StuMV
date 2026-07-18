<?php

use App\Livewire\Oidc\EditOidcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the edit form is pre-filled with the client\'s current values', function (): void {
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['scopes' => ['openid', 'profile']])->save();
    actingAsSuperAdmin();

    Livewire::test(EditOidcClient::class, ['client' => $client])
        ->assertSet('name', 'My SSO App')
        ->assertSet('redirectUris', 'https://app.example.com/callback')
        ->assertSet('scopes', ['openid', 'profile']);
});

test('a client\'s name, redirect URIs and scopes can be updated', function (): void {
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['scopes' => ['openid']])->save();
    actingAsSuperAdmin();

    Livewire::test(EditOidcClient::class, ['client' => $client])
        ->set('name', 'My Renamed App')
        ->set('redirectUris', "https://app.example.com/callback\nhttps://app.example.com/second-callback")
        ->set('scopes', ['openid', 'email'])
        ->call('save');

    $client->refresh();

    expect($client->name)->toBe('My Renamed App')
        ->and($client->redirect_uris)->toBe(['https://app.example.com/callback', 'https://app.example.com/second-callback'])
        ->and($client->scopes)->toBe(['openid', 'email']);
});

test('editing a client requires at least one scope', function (): void {
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['scopes' => ['openid']])->save();
    actingAsSuperAdmin();

    Livewire::test(EditOidcClient::class, ['client' => $client])
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('a directory API (community-scoped) client cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Directory API Client');
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsSuperAdmin();

    Livewire::test(EditOidcClient::class, ['client' => $client])
        ->assertStatus(404);
});

test('a non-superadmin cannot edit an OIDC client', function (): void {
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('oidc-clients.edit', ['client' => $client->id]))->assertForbidden();
});
