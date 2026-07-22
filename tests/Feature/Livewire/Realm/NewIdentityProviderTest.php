<?php

use App\Livewire\Realm\NewSsoProvider;
use App\Models\RealmSsoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new identity provider can be registered', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewSsoProvider::class, ['realm' => $community])
        ->set('name', 'My University')
        ->set('issuer', 'https://idp.example.test/')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->set('groups_claim', 'groups')
        ->set('enabled', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.sso-providers', ['realm' => $community->getShortCode()]));

    $provider = RealmSsoProvider::where('name', 'My University')->firstOrFail();

    expect($provider->realm)->toBe($community->getShortCode())
        ->and($provider->issuer)->toBe('https://idp.example.test') // trailing slash trimmed
        ->and($provider->client_id)->toBe('client-id')
        ->and($provider->client_secret)->toBe('client-secret')
        ->and($provider->groups_claim)->toBe('groups')
        ->and($provider->enabled)->toBeTrue();
});

test('registering an identity provider requires a name', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewSsoProvider::class, ['realm' => $community])
        ->set('issuer', 'https://idp.example.test')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('registering an identity provider requires a valid issuer URL', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewSsoProvider::class, ['realm' => $community])
        ->set('name', 'My University')
        ->set('issuer', 'not-a-url')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->call('save')
        ->assertHasErrors(['issuer' => 'url']);
});

test('a non-admin cannot register an identity provider', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.sso-providers.new', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no identity provider feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.sso-providers.new', ['realm' => 'admin']))->assertNotFound();
});
