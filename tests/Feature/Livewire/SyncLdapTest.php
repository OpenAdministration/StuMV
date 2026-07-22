<?php

use App\Livewire\SyncLdap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a success toast is shown after syncing LDAP', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap')
        ->assertDispatched('toast-show');
});

test('syncing only covers the given realm, not every realm', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Artisan::shouldReceive('call')
        ->once()
        ->with('ldap:sync-roles', ['community' => $community->getShortCode()])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('ldap:sync-groups', ['community' => $community->getShortCode()])
        ->andReturn(0);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap');
});

test('an admin of a different realm cannot use this component for a realm they don\'t administer', function (): void {
    $ownRealm = newCommunity();
    $otherRealm = newCommunity();
    actingAsAdmin($ownRealm);

    Livewire::test(SyncLdap::class, ['uid' => $otherRealm->getShortCode()])
        ->assertStatus(403);
});

test('a moderator cannot use this component', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('a member cannot use this component', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('a super admin can use this component for any realm', function (): void {
    $community = newCommunity();
    actingAsSuperAdmin();

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap')
        ->assertDispatched('toast-show');
});

test('the sync-ldap button is shown to an admin on their own realm\'s dashboard', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertSee('syncLdap', escape: false);
});

test('the sync-ldap button is hidden from a moderator', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertDontSee('syncLdap', escape: false);
});

test('the sync-ldap button is hidden from a plain member', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertDontSee('syncLdap', escape: false);
});
