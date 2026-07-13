<?php

use App\Ldap\Domain;
use App\Livewire\Realm\ListDomains;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function makeDomain(string $uid, string $dc): Domain
{
    $domain = Domain::make(['dc' => $dc]);
    $domain->setDn("dc=$dc,".Domain::dnRoot($uid));
    $domain->save();
    TestLdap::track($domain);

    return $domain;
}

test('domains are listed without using the LDAP slice/VLV query', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    makeDomain($uid, 'alpha.test');
    makeDomain($uid, 'beta.test');
    actingAsModerator($community);

    Livewire::test(ListDomains::class, ['uid' => $community])
        ->assertSee('alpha.test')
        ->assertSee('beta.test');
});

test('the domain search filters the list', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    makeDomain($uid, 'alpha.test');
    makeDomain($uid, 'beta.test');
    actingAsModerator($community);

    Livewire::test(ListDomains::class, ['uid' => $community])
        ->set('search', 'alpha')
        ->assertSee('alpha.test')
        ->assertDontSee('beta.test');
});

test('domains are sorted by short name ascending by default', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    makeDomain($uid, 'zeta.test');
    makeDomain($uid, 'alpha.test');
    makeDomain($uid, 'mike.test');
    actingAsModerator($community);

    $dcs = Livewire::test(ListDomains::class, ['uid' => $community])
        ->viewData('domains')
        ->map(fn ($domain) => $domain->getFirstAttribute('dc'))
        ->values()
        ->all();

    expect($dcs)->toBe(['alpha.test', 'mike.test', 'zeta.test']);
});

test('sortBy toggles direction and re-sorts the domains descending', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    makeDomain($uid, 'zeta.test');
    makeDomain($uid, 'alpha.test');
    makeDomain($uid, 'mike.test');
    actingAsModerator($community);

    $dcs = Livewire::test(ListDomains::class, ['uid' => $community])
        ->call('sortBy', 'dc')
        ->assertSet('sortDirection', 'desc')
        ->viewData('domains')
        ->map(fn ($domain) => $domain->getFirstAttribute('dc'))
        ->values()
        ->all();

    expect($dcs)->toBe(['zeta.test', 'mike.test', 'alpha.test']);
});

test('the domains list shows a warning callout when there are no domains', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(ListDomains::class, ['uid' => $community])
        ->assertSeeHtml('data-flux-callout')
        ->assertSee(__('domain.nothing_found'));
});

test('the domains list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    foreach (range(1, 15) as $i) {
        makeDomain($uid, sprintf('domain%02d.test', $i));
    }
    actingAsModerator($community);

    $component = Livewire::test(ListDomains::class, ['uid' => $community]);

    expect($component->viewData('domains'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('domains');
    expect($page2)->toHaveCount(5);
});
