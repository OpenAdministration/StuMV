<?php

use App\Ldap\SuperUserGroup;
use App\Livewire\Realm\ListRealms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the search filters the community list by shortcode', function (): void {
    $alpha = newCommunity('alpha'.bin2hex(random_bytes(3)));
    $beta = newCommunity('beta'.bin2hex(random_bytes(3)));
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->set('search', $alpha->getShortCode())
        ->assertSee($alpha->getShortCode())
        ->assertDontSee($beta->getShortCode());
});

test('the search filters the community list by name', function (): void {
    $alpha = newCommunity();
    $alpha->fill(['description' => 'Alpha Community'])->save();
    $beta = newCommunity();
    $beta->fill(['description' => 'Beta Community'])->save();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->set('search', 'Alpha Community')
        ->assertSee('Alpha Community')
        ->assertDontSee('Beta Community');
});

test('communities are sorted by name (description) ascending by default', function (): void {
    $zeta = newCommunity();
    $zeta->fill(['description' => 'Zeta Community'])->save();
    $alpha = newCommunity();
    $alpha->fill(['description' => 'Alpha Community'])->save();
    actingAsSuperAdmin();

    $names = Livewire::test(ListRealms::class)
        ->viewData('realms')
        ->map(fn ($community) => $community->getLongName())
        ->values()
        ->all();

    expect(array_search('Alpha Community', $names, true))
        ->toBeLessThan(array_search('Zeta Community', $names, true));
});

test('sortBy toggles direction and re-sorts the community list by name', function (): void {
    $zeta = newCommunity();
    $zeta->fill(['description' => 'Zeta Community'])->save();
    $alpha = newCommunity();
    $alpha->fill(['description' => 'Alpha Community'])->save();
    actingAsSuperAdmin();

    $names = Livewire::test(ListRealms::class)
        ->call('sortBy', 'description')
        ->assertSet('sortDirection', 'desc')
        ->viewData('realms')
        ->map(fn ($community) => $community->getLongName())
        ->values()
        ->all();

    expect(array_search('Zeta Community', $names, true))
        ->toBeLessThan(array_search('Alpha Community', $names, true));
});

test('sortBy switches to sorting by shortcode when that column is clicked', function (): void {
    $zeta = newCommunity('zeta'.bin2hex(random_bytes(3)));
    $alpha = newCommunity('alpha'.bin2hex(random_bytes(3)));
    actingAsSuperAdmin();

    $codes = Livewire::test(ListRealms::class)
        ->call('sortBy', 'ou')
        ->assertSet('sortField', 'ou')
        ->assertSet('sortDirection', 'asc')
        ->viewData('realms')
        ->map(fn ($community) => $community->getShortCode())
        ->values()
        ->all();

    expect(array_search($alpha->getShortCode(), $codes, true))
        ->toBeLessThan(array_search($zeta->getShortCode(), $codes, true));
});

test('the community name is only a clickable link to enter when the user can actually enter', function (): void {
    $canEnter1 = newCommunity('cane1'.bin2hex(random_bytes(3)));
    $canEnter2 = newCommunity('cane2'.bin2hex(random_bytes(3)));
    $cannotEnter = newCommunity('cant'.bin2hex(random_bytes(3)));

    // A member of two communities (not just one) so mount() doesn't
    // auto-redirect straight to a single dashboard.
    $ldapUser = TestLdap::makeUser();
    TestLdap::attach($canEnter1->membersGroup(), $ldapUser);
    TestLdap::attach($canEnter2->membersGroup(), $ldapUser);
    $this->actingAs(TestLdap::databaseUser($ldapUser));

    $html = Livewire::test(ListRealms::class)->html();

    // Each row always has the (possibly disabled) "Enter" button carrying
    // this wire:click - a community the user can enter additionally wraps
    // its name in a matching link, so the marker appears twice there but
    // only once (the button) for one they can't enter.
    expect(substr_count($html, "wire:click=\"enter('{$canEnter1->getShortCode()}')\""))->toBe(2)
        ->and(substr_count($html, "wire:click=\"enter('{$cannotEnter->getShortCode()}')\""))->toBe(1);
});

test('the "only mine" switch hides communities the user is not a member of', function (): void {
    $mine1 = newCommunity('mine1'.bin2hex(random_bytes(3)));
    $mine2 = newCommunity('mine2'.bin2hex(random_bytes(3)));
    $notMine = newCommunity('notm'.bin2hex(random_bytes(3)));

    // A member of two communities (not just one) so mount() doesn't
    // auto-redirect straight to a single dashboard.
    $ldapUser = TestLdap::makeUser();
    TestLdap::attach($mine1->membersGroup(), $ldapUser);
    TestLdap::attach($mine2->membersGroup(), $ldapUser);
    $this->actingAs(TestLdap::databaseUser($ldapUser));

    $component = Livewire::test(ListRealms::class)
        ->assertSee($mine1->getShortCode())
        ->assertSee($mine2->getShortCode())
        ->assertSee($notMine->getShortCode());

    $component->set('showOnlyMine', true)
        ->assertSee($mine1->getShortCode())
        ->assertSee($mine2->getShortCode())
        ->assertDontSee($notMine->getShortCode());
});

test('the "only mine" switch also applies for a super admin', function (): void {
    $mine = newCommunity('mine'.bin2hex(random_bytes(3)));
    $notMine = newCommunity('notm'.bin2hex(random_bytes(3)));

    $ldapUser = TestLdap::makeUser();
    TestLdap::attach(SuperUserGroup::group(), $ldapUser);
    TestLdap::attach($mine->membersGroup(), $ldapUser);
    $this->actingAs(TestLdap::databaseUser($ldapUser));

    Livewire::test(ListRealms::class)
        ->set('showOnlyMine', true)
        ->assertSee($mine->getShortCode())
        ->assertDontSee($notMine->getShortCode());
});

test('the community list is paginated to 10 per page', function (): void {
    // Other fixture communities (e.g. the baked-in "demo"/"testcom" realms)
    // may already exist, so the expected page-2 count is derived from the
    // paginator's own total rather than assumed.
    foreach (range(1, 11) as $i) {
        newCommunity(sprintf('pcom%02d', $i));
    }
    actingAsSuperAdmin();

    $component = Livewire::test(ListRealms::class);
    $page1 = $component->viewData('realms');

    expect($page1)->toHaveCount(10);

    $total = $page1->total();
    expect($total)->toBeGreaterThanOrEqual(11);

    $component->call('gotoPage', 2);
    expect($component->viewData('realms'))->toHaveCount(min(10, $total - 10));
});
