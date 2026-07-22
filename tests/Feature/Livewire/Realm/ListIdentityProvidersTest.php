<?php

use App\Livewire\Realm\ListIdentityProviders;
use App\Models\RealmIdentityProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('only this realm\'s identity providers are listed, not another realm\'s', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    makeIdentityProvider($community->getShortCode(), 'My IdP');
    makeIdentityProvider($otherCommunity->getShortCode(), 'Other Realm IdP');
    actingAsAdmin($community);

    Livewire::test(ListIdentityProviders::class, ['realm' => $community])
        ->assertSee('My IdP')
        ->assertDontSee('Other Realm IdP');
});

test('a provider can be deleted', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(ListIdentityProviders::class, ['realm' => $community])
        ->call('deletePrepare', $provider->id)
        ->call('deleteCommit');

    expect(RealmIdentityProvider::find($provider->id))->toBeNull();
});

test('an admin cannot delete another realm\'s identity provider', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeIdentityProvider($otherCommunity->getShortCode(), 'Other Realm IdP');
    actingAsAdmin($community);

    Livewire::test(ListIdentityProviders::class, ['realm' => $community])
        ->call('deletePrepare', $provider->id);
})->throws(ModelNotFoundException::class);

test('a non-admin cannot view the identity provider list', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.identity-providers', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no identity provider feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.identity-providers', ['realm' => 'admin']))->assertNotFound();
});
