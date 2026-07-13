<?php

use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('searching auto-unfolds branches down to the match without manual toggling', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $grandchild = TestLdap::makeCommittee($community, 'grandchild', parentDn: $child->getDn());
    // sibling branch that must not appear once we search for "grandchild"
    $other = TestLdap::makeCommittee($community, 'other');
    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->assertDontSee('Committee grandchild')
        ->set('search', 'grandchild')
        ->assertSee('Committee parent')
        ->assertSee('Committee child')
        ->assertSee('Committee grandchild')
        ->assertDontSee('Committee other');
});

test('toggle buttons are hidden while searching but shown otherwise', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    actingAsModerator($community);

    $toggleMarker = "toggleChildren('{$parent->getDn()}')";

    Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->assertSeeHtml($toggleMarker)
        ->set('search', 'child')
        ->assertDontSeeHtml($toggleMarker);
});

test('clearing the search restores manual fold state', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->assertDontSee('Committee child')
        ->set('search', 'child')
        ->assertSee('Committee child')
        ->set('search', '')
        ->assertDontSee('Committee child');
});
