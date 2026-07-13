<?php

use App\Livewire\Realm\ListAdmins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('admins are sorted by name ascending by default', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser($uid));
    }
    actingAsModerator($community);

    $cns = Livewire::test(ListAdmins::class, ['uid' => $community])
        ->call('loadAdmins')
        ->viewData('realm_admins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test alpha', 'Test mike', 'Test zeta']);
});

test('sortBy toggles direction and re-sorts the admins descending', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser($uid));
    }
    actingAsModerator($community);

    $cns = Livewire::test(ListAdmins::class, ['uid' => $community])
        ->call('loadAdmins')
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'desc')
        ->viewData('realm_admins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test zeta', 'Test mike', 'Test alpha']);
});

test('the admins list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (range(1, 15) as $i) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser(sprintf('adm%02d', $i)));
    }
    actingAsModerator($community);

    $component = Livewire::test(ListAdmins::class, ['uid' => $community])->call('loadAdmins');

    expect($component->viewData('realm_admins'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('realm_admins');
    expect($page2)->toHaveCount(5);
});

test('the search field filters the admins list', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    TestLdap::attach($adminsGroup, TestLdap::makeUser('alphaadmin'));
    TestLdap::attach($adminsGroup, TestLdap::makeUser('betaadmin'));
    actingAsModerator($community);

    Livewire::test(ListAdmins::class, ['uid' => $community])
        ->call('loadAdmins')
        ->set('search', 'alphaadmin')
        ->assertSee('Test alphaadmin')
        ->assertDontSee('Test betaadmin');
});
