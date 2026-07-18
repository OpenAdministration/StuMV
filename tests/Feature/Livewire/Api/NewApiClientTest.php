<?php

use App\Livewire\Api\NewApiClient;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new client-credentials client can be registered for the community', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'My New Client')
        ->set('scopes', ['committees', 'users'])
        ->call('save');

    $client = PassportClient::where('community_uid', $uid)->where('name', 'My New Client')->firstOrFail();

    expect($client->grant_types)->toBe(['client_credentials'])
        ->and($client->scopes)->toBe(['committees', 'users'])
        ->and($client->revoked)->toBeFalse();
});

test('the plaintext secret is shown once after creation', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'My New Client')
        ->set('scopes', ['committees'])
        ->call('save')
        ->assertSet('createdClientId', fn ($id) => filled($id))
        ->assertSet('createdClientSecret', fn ($secret) => filled($secret));
});

test('the secret reveal panel actually renders the secret', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $component = Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'My New Client')
        ->set('scopes', ['committees'])
        ->call('save');

    $component->assertSee(__('api_clients.client_secret'))
        ->assertSee($component->get('createdClientSecret'));
});

test('registering a client requires a name', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', '')
        ->set('scopes', ['committees'])
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('registering a client requires at least one scope', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'My New Client')
        ->set('scopes', [])
        ->call('save')
        ->assertHasErrors(['scopes' => 'required']);
});

test('registering a client rejects scopes outside the allowed directory scopes', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'My New Client')
        ->set('scopes', ['profile'])
        ->call('save')
        ->assertHasErrors(['scopes.0' => 'in']);
});
