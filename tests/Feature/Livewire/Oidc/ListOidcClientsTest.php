<?php

use App\Livewire\Oidc\ListOidcClients;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeOidcClient(string $name, string $communityUid, array $scopes = ['openid']): PassportClient
{
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $communityUid, 'scopes' => $scopes])->save();

    return $client;
}

test('only this realm\'s OIDC clients are listed, not its API clients or another realm\'s OIDC clients', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    makeOidcClient('My SSO App', $community->getShortCode());
    makeOidcClient('Other Realm SSO App', $otherCommunity->getShortCode());
    $directoryClient = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Directory API Client');
    $directoryClient->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsAdmin($community);

    Livewire::test(ListOidcClients::class, ['realm' => $community])
        ->assertSee('My SSO App')
        ->assertDontSee('Other Realm SSO App')
        ->assertDontSee('Directory API Client');
});

test('a client can be revoked', function (): void {
    $community = newCommunity();
    $client = makeOidcClient('My SSO App', $community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(ListOidcClients::class, ['realm' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect($client->fresh()->revoked)->toBeTrue();
});

test('a revoked client can no longer authenticate', function (): void {
    $community = newCommunity();
    $client = makeOidcClient('My SSO App', $community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(ListOidcClients::class, ['realm' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect(resolve(ClientRepository::class)->findActive($client->id))->toBeNull();
});

test('an admin cannot revoke another realm\'s OIDC client', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $client = makeOidcClient('Other Realm SSO App', $otherCommunity->getShortCode());
    actingAsAdmin($community);

    Livewire::test(ListOidcClients::class, ['realm' => $community])
        ->call('revokePrepare', $client->id);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('a non-admin cannot view the OIDC client list', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.oidc-clients', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no OIDC clients feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.oidc-clients', ['realm' => 'admin']))->assertNotFound();
});
