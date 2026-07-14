<?php

use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a collapsed committee shows an expand arrow but not its children', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn())->fill(['description' => 'Child Committee'])->save();

    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->assertSee(__('committees.unfoldSubItems', ['committee' => 'Committee parent']))
        ->assertDontSee('Child Committee')
        ->call('toggleChildren', $parent->getDn())
        ->assertSee('Child Committee')
        ->assertSee(__('committees.foldSubItems', ['committee' => 'Committee parent']));
});

test('a childless committee shows no expand arrow', function (): void {
    $community = newCommunity();
    TestLdap::makeCommittee($community, 'lonely');

    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->assertDontSee(__('committees.unfoldSubItems', ['committee' => 'Committee lonely']))
        ->assertDontSee(__('committees.foldSubItems', ['committee' => 'Committee lonely']));
});
