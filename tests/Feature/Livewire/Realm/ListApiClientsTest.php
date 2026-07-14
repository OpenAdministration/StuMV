<?php

use App\Livewire\Realm\ListApiClients;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeApiClient(string $uid, string $name, array $scopes = ['committees']): PassportClient
{
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient($name);
    $client->forceFill(['community_uid' => $uid, 'scopes' => $scopes])->save();

    return $client;
}

test('only clients registered for this community are listed', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    makeApiClient($uid, 'My Client');
    makeApiClient($otherCommunity->getShortCode(), 'Someone Elses Client');
    actingAsAdmin($community);

    Livewire::test(ListApiClients::class, ['uid' => $community])
        ->assertSee('My Client')
        ->assertDontSee('Someone Elses Client');
});

test('a client can be revoked', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = makeApiClient($uid, 'My Client');
    actingAsAdmin($community);

    Livewire::test(ListApiClients::class, ['uid' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect($client->fresh()->revoked)->toBeTrue();
});

test('a revoked client can no longer authenticate against the directory API', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $client = makeApiClient($uid, 'My Client');
    actingAsAdmin($community);

    Livewire::test(ListApiClients::class, ['uid' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    expect(resolve(ClientRepository::class)->findActive($client->id))->toBeNull();
});
