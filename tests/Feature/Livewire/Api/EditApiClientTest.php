<?php

use App\Livewire\Api\EditApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the edit form is pre-filled with the client\'s current values', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('My API Client');
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['committees', 'groups']])->save();
    actingAsAdmin($community);

    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->assertSet('name', 'My API Client')
        ->assertSet('scopes', ['committees', 'groups']);
});

test('a client\'s name and scopes can be updated', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('My API Client');
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['committees']])->save();
    actingAsAdmin($community);

    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->set('name', 'My Renamed Client')
        ->set('scopes', ['committees', 'users'])
        ->call('save');

    $client->refresh();

    expect($client->name)->toBe('My Renamed Client')
        ->and($client->scopes)->toBe(['committees', 'users']);
});

test('editing a client requires at least one scope', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('My API Client');
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['committees']])->save();
    actingAsAdmin($community);

    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('editing a client rejects scopes outside the allowed directory scopes', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('My API Client');
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['committees']])->save();
    actingAsAdmin($community);

    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->set('scopes', ['profile'])
        ->call('save')
        ->assertHasErrors(['scopes.0' => 'in']);
});

test('a client belonging to a different community cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('Someone Elses Client');
    $client->forceFill(['community_uid' => $otherCommunity->getShortCode(), 'scopes' => ['committees']])->save();
    actingAsAdmin($community);

    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->assertStatus(404);
});

test('a non-admin cannot edit an api client', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createClientCredentialsGrantClient('My API Client');
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['committees']])->save();
    actingAsMember($community);

    $this->get(route('realms.api-clients.edit', ['realm' => $community->getShortCode(), 'client' => $client->id]))->assertForbidden();
});
