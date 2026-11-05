<?php

use App\Livewire\SyncMailman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('syncing runs mailman:sync-lists for this realm only and shows a success toast', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Artisan::shouldReceive('call')
        ->once()
        ->with('mailman:sync-lists', ['realm' => $community->getShortCode()])
        ->andReturn(0);

    Livewire::test(SyncMailman::class, ['uid' => $community->getShortCode()])
        ->call('syncMailman')
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'success');
});

test('a failed sync shows an error toast', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Artisan::shouldReceive('call')->once()->andReturn(1);

    Livewire::test(SyncMailman::class, ['uid' => $community->getShortCode()])
        ->call('syncMailman')
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'danger');
});

test('an admin of a different realm cannot use this component', function (): void {
    $ownRealm = newCommunity();
    $otherRealm = newCommunity();
    actingAsAdmin($ownRealm);

    Livewire::test(SyncMailman::class, ['uid' => $otherRealm->getShortCode()])
        ->assertStatus(403);
});

test('a moderator cannot use this component', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(SyncMailman::class, ['uid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('the header shows the button to admins only when Mailman is configured', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $url = route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]);

    config(['services.mailman.api_url' => null]);
    $this->get($url)->assertOk()->assertDontSeeLivewire(SyncMailman::class);

    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $this->get($url)->assertOk()->assertSeeLivewire(SyncMailman::class);
});
