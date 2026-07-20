<?php

use App\Livewire\Realm\ListSsoProviders;
use App\Models\RealmSsoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('only this realm\'s identity providers are listed, not another realm\'s', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    makeSsoProvider($community->getShortCode(), 'My IdP');
    makeSsoProvider($otherCommunity->getShortCode(), 'Other Realm IdP');
    actingAsAdmin($community);

    Livewire::test(ListSsoProviders::class, ['realm' => $community])
        ->assertSee('My IdP')
        ->assertDontSee('Other Realm IdP');
});

test('a provider can be deleted', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(ListSsoProviders::class, ['realm' => $community])
        ->call('deletePrepare', $provider->id)
        ->call('deleteCommit');

    expect(RealmSsoProvider::find($provider->id))->toBeNull();
});

test('an admin cannot delete another realm\'s identity provider', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeSsoProvider($otherCommunity->getShortCode(), 'Other Realm IdP');
    actingAsAdmin($community);

    Livewire::test(ListSsoProviders::class, ['realm' => $community])
        ->call('deletePrepare', $provider->id);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('a non-admin cannot view the identity provider list', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.sso-providers', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no identity provider feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.sso-providers', ['realm' => 'admin']))->assertNotFound();
});
