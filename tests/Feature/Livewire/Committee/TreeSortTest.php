<?php

use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('top-level committees are sorted by name', function (): void {
    $community = newCommunity();
    TestLdap::makeCommittee($community, 'zzz')->fill(['description' => 'Zebra'])->save();
    TestLdap::makeCommittee($community, 'aaa')->fill(['description' => 'Apple'])->save();
    TestLdap::makeCommittee($community, 'mmm')->fill(['description' => 'Mango'])->save();
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    $posApple = strpos($html, 'Apple');
    $posMango = strpos($html, 'Mango');
    $posZebra = strpos($html, 'Zebra');

    expect($posApple)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posZebra);
});

test('children within a folder are sorted by name too', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    TestLdap::makeCommittee($community, 'c1', parentDn: $parent->getDn())->fill(['description' => 'Charlie'])->save();
    TestLdap::makeCommittee($community, 'c2', parentDn: $parent->getDn())->fill(['description' => 'Alice'])->save();
    TestLdap::makeCommittee($community, 'c3', parentDn: $parent->getDn())->fill(['description' => 'Bob'])->save();
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->call('toggleChildren', $parent->getDn())
        ->html();

    $posAlice = strpos($html, 'Alice');
    $posBob = strpos($html, 'Bob');
    $posCharlie = strpos($html, 'Charlie');

    expect($posAlice)->toBeLessThan($posBob)
        ->and($posBob)->toBeLessThan($posCharlie);
});
