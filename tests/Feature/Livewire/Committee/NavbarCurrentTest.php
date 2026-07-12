<?php

use App\Livewire\Committee\ListCommitteesList;
use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the tree view marks the tree navbar link as current', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    preg_match('/<a href="[^"]*\/committees\?[^"]*"[^>]*>/', $html, $treeLink);
    preg_match('/<a href="[^"]*\/committees\/list\?[^"]*"[^>]*>/', $html, $listLink);

    expect($treeLink[0])->toContain('data-current="data-current"')
        ->and($listLink[0])->not->toContain('data-current="');
});

test('the list view marks the list navbar link as current', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesList::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    preg_match('/<a href="[^"]*\/committees\?[^"]*"[^>]*>/', $html, $treeLink);
    preg_match('/<a href="[^"]*\/committees\/list\?[^"]*"[^>]*>/', $html, $listLink);

    expect($treeLink[0])->not->toContain('data-current="')
        ->and($listLink[0])->toContain('data-current="data-current"');
});
