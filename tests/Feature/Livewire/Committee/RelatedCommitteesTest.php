<?php

use App\Livewire\Committee\ListRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * stura          other
 * ├── finanzen
 * │   └── kasse
 * └── kultur
 */
function committeeTree(): array
{
    $community = newCommunity();
    $stura = TestLdap::makeCommittee($community, 'stura');
    TestLdap::makeCommittee($community, 'other');
    $finanzen = TestLdap::makeCommittee($community, 'finanzen', parentDn: $stura->getDn());
    TestLdap::makeCommittee($community, 'kultur', parentDn: $stura->getDn());
    TestLdap::makeCommittee($community, 'kasse', parentDn: $finanzen->getDn());

    return [$community, fn (string $ou): string => route('committees.roles', ['realm' => $community->getShortCode(), 'ou' => $ou])];
}

test('a nested committee links to its parent, siblings and children', function (): void {
    [$community, $url] = committeeTree();
    actingAsMember($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'finanzen'])
        ->assertSee(__('committees.parent_committee'))
        ->assertSeeHtml('href="'.$url('stura').'"')
        ->assertSee(__('committees.sibling_committees'))
        ->assertSeeHtml('href="'.$url('kultur').'"')
        ->assertSee(__('committees.child_committees'))
        ->assertSeeHtml('href="'.$url('kasse').'"')
        ->assertDontSeeHtml('href="'.$url('finanzen').'"')
        ->assertDontSeeHtml('href="'.$url('other').'"');
});

test('a top-level committee shows that it has no parent and other top-level committees as siblings', function (): void {
    [$community, $url] = committeeTree();
    actingAsMember($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'stura'])
        ->assertSee(__('committees.no_parent_committee'))
        ->assertSeeHtml('href="'.$url('other').'"')
        ->assertSeeHtml('href="'.$url('finanzen').'"')
        ->assertSeeHtml('href="'.$url('kultur').'"')
        ->assertDontSeeHtml('href="'.$url('kasse').'"');
});

test('all three boxes are shown, empty ones with a notice', function (): void {
    [$community, $url] = committeeTree();
    actingAsMember($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'kasse'])
        ->assertSeeHtml('href="'.$url('finanzen').'"')
        ->assertSee(__('committees.sibling_committees'))
        ->assertSee(__('committees.no_sibling_committees'))
        ->assertSee(__('committees.child_committees'))
        ->assertSee(__('committees.no_child_committees'));
});
