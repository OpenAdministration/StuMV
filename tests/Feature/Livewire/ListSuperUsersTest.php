<?php

use App\Ldap\SuperUserGroup;
use App\Livewire\ListSuperUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the list links each super admin to their profile', function (): void {
    $superadmin = actingAsSuperAdmin();

    Livewire::test(ListSuperUsers::class)
        ->assertSeeHtml(route('profile', ['username' => $superadmin->username]));
});

test('superadmins are sorted by name ascending by default', function (): void {
    actingAsSuperAdmin();
    $group = SuperUserGroup::group();
    foreach (['zetasuper', 'alphasuper', 'mikesuper'] as $uid) {
        TestLdap::attach($group, TestLdap::makeUser($uid));
    }

    $cns = Livewire::test(ListSuperUsers::class)
        ->set('search', 'super')
        ->viewData('superadmins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test alphasuper', 'Test mikesuper', 'Test zetasuper']);
});

test('sortBy toggles direction and re-sorts the superadmins descending', function (): void {
    actingAsSuperAdmin();
    $group = SuperUserGroup::group();
    foreach (['zetasuper', 'alphasuper', 'mikesuper'] as $uid) {
        TestLdap::attach($group, TestLdap::makeUser($uid));
    }

    $cns = Livewire::test(ListSuperUsers::class)
        ->set('search', 'super')
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'desc')
        ->viewData('superadmins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test zetasuper', 'Test mikesuper', 'Test alphasuper']);
});

test('the superadmins list is paginated to 10 per page', function (): void {
    actingAsSuperAdmin();
    $group = SuperUserGroup::group();
    foreach (range(1, 15) as $i) {
        TestLdap::attach($group, TestLdap::makeUser(sprintf('psuper%02d', $i)));
    }

    $component = Livewire::test(ListSuperUsers::class)->set('search', 'psuper');

    expect($component->viewData('superadmins'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('superadmins');
    expect($page2)->toHaveCount(5);
});

test('the search field filters the superadmins list', function (): void {
    actingAsSuperAdmin();
    $group = SuperUserGroup::group();
    TestLdap::attach($group, TestLdap::makeUser('alphasuperadmin'));
    TestLdap::attach($group, TestLdap::makeUser('betasuperadmin'));

    Livewire::test(ListSuperUsers::class)
        ->set('search', 'alphasuperadmin')
        ->assertSee('Test alphasuperadmin')
        ->assertDontSee('Test betasuperadmin');
});
