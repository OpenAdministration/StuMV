<?php

use App\Livewire\SyncMailman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
    Artisan::shouldReceive('output')->once()->andReturn('Failed to add a@example.org: boom');
    Log::spy();

    Livewire::test(SyncMailman::class, ['uid' => $community->getShortCode()])
        ->call('syncMailman')
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'danger');

    Log::shouldHaveReceived('error')->with('mailman:sync-lists failed', Mockery::on(fn (array $context): bool => $context['output'] === 'Failed to add a@example.org: boom'));
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
