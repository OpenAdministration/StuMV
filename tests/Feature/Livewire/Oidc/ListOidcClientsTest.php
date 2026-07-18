<?php

use App\Livewire\Oidc\ListOidcClients;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeOidcClient(string $name, array $scopes = ['openid']): PassportClient
{
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://app.example.com/callback']);
    $client->forceFill(['scopes' => $scopes])->save();

    return $client;
}

test('only community-independent clients are listed, not the directory API ones', function (): void {
    $community = newCommunity();
    makeOidcClient('My SSO App');
    $directoryClient = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Directory API Client');
    $directoryClient->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsSuperAdmin();

    Livewire::test(ListOidcClients::class)
        ->assertSee('My SSO App')
        ->assertDontSee('Directory API Client');
});

test('a client can be revoked', function (): void {
    $client = makeOidcClient('My SSO App');
    actingAsSuperAdmin();

    Livewire::test(ListOidcClients::class)
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect($client->fresh()->revoked)->toBeTrue();
});

test('a revoked client can no longer authenticate', function (): void {
    $client = makeOidcClient('My SSO App');
    actingAsSuperAdmin();

    Livewire::test(ListOidcClients::class)
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect(resolve(ClientRepository::class)->findActive($client->id))->toBeNull();
});

test('a non-superadmin cannot view the OIDC client list', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('oidc-clients.list'))->assertForbidden();
});
