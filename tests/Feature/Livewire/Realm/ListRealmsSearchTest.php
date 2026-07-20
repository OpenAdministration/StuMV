<?php

use App\Livewire\Realm\ListRealms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    // A regular member of exactly one realm never actually sees this
    // picker rendered - mount() redirects them straight to their one
    // dashboard before render() runs at all (that's HomeRedirectTest's
    // "single community" case). The only actor that reaches render() while
    // having an "enterable" community at all is a superadmin, for whom
    // every row is enterable ($canEnter === true unconditionally - see
    // CommunityPolicy's admin-realm-wide rights) - this pins that.
    $community = newCommunity('cane'.bin2hex(random_bytes(3)));
    actingAsSuperAdmin();

    $html = Livewire::test(ListRealms::class)->html();

    // Each row always has the (possibly disabled) "Enter" button carrying
    // this wire:click - an enterable community additionally wraps its name
    // in a matching link, so the marker appears twice there.
    expect(substr_count($html, "wire:click=\"enter('{$community->getShortCode()}')\""))->toBe(2);
});

test('the "only mine" switch hides every community for a user who belongs to none', function (): void {
    // Same reachability constraint as above: a regular member of exactly
    // one realm is redirected away by mount() before this is observable,
    // and a physical account can never be a member of more than one realm
    // (membership is its own DN location). The only regular-user state
    // that reaches render() at all is "member of zero realms" (e.g. an
    // account mid-verification, not yet realm-scoped) - "only mine"
    // correctly hides everything for it.
    $notMine = newCommunity('notm'.bin2hex(random_bytes(3)));
    // An LDAP entry that exists but isn't placed under any community's
    // People branch (e.g. left "unassigned" by the realm-split migration).
    $ldapUser = Tests\Support\TestLdap::makeUser();
    $this->actingAs(Tests\Support\TestLdap::databaseUser($ldapUser));

    Livewire::test(ListRealms::class)
        ->assertSee($notMine->getShortCode())
        ->set('showOnlyMine', true)
        ->assertDontSee($notMine->getShortCode());
});

test('the "only mine" switch shows just the admin realm for a super admin', function (): void {
    // A superadmin's only physical membership is the dedicated "admin"
    // realm itself (see Community::ADMIN_REALM_UID) - they can administer
    // every other realm via CommunityPolicy, but aren't a "member" of any
    // of them by location, so "only mine" narrows down to just that one.
    $notMine = newCommunity('notm'.bin2hex(random_bytes(3)));
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->set('showOnlyMine', true)
        ->assertSee(\App\Ldap\Community::ADMIN_REALM_UID)
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
