<?php

use App\Livewire\Realm\NewIdentityProvider;
use App\Models\RealmIdentityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a new identity provider can be registered', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
        ->set('name', 'My University')
        ->set('issuer', 'https://idp.example.test/')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->set('groups_claim', 'groups')
        ->set('enabled', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.identity-providers', ['realm' => $community->getShortCode()]));

    $provider = RealmIdentityProvider::where('name', 'My University')->firstOrFail();

    expect($provider->realm)->toBe($community->getShortCode())
        ->and($provider->issuer)->toBe('https://idp.example.test') // trailing slash trimmed
        ->and($provider->client_id)->toBe('client-id')
        ->and($provider->client_secret)->toBe('client-secret')
        ->and($provider->groups_claim)->toBe('groups')
        ->and($provider->enabled)->toBeTrue();
});

test('extra authorize params can be entered as key=value lines and are stored as an array', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
        ->set('name', 'My University')
        ->set('issuer', 'https://idp.example.test')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->set('groups_claim', 'groups')
        ->set('extra_authorize_params_input', "kc_idp_hint=my-upstream-idp\nprompt=login")
        ->call('save')
        ->assertHasNoErrors();

    $provider = RealmIdentityProvider::where('name', 'My University')->firstOrFail();

    expect($provider->extra_authorize_params)->toBe([
        'kc_idp_hint' => 'my-upstream-idp',
        'prompt' => 'login',
    ]);
});

test('a malformed extra authorize params line is rejected', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
        ->set('name', 'My University')
        ->set('issuer', 'https://idp.example.test')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->set('extra_authorize_params_input', 'not-a-key-value-line')
        ->call('save')
        ->assertHasErrors(['extra_authorize_params_input']);
});

test('registering an identity provider requires a name', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
        ->set('issuer', 'https://idp.example.test')
        ->set('client_id', 'client-id')
        ->set('client_secret', 'client-secret')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('registering an identity provider requires a valid issuer URL', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
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

    $this->get(route('realms.identity-providers.new', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no identity provider feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.identity-providers.new', ['realm' => 'admin']))->assertNotFound();
});
