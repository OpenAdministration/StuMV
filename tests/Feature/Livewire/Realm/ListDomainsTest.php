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
