<?php

use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * Committees can only be created/edited/deleted by community moderators -
 * committee moderators are scoped to roles/role memberships within their
 * committee, not the committee itself (see CommitteePolicy::edit()/delete()).
 * ListCommitteesTree::buildNode() computes a single flat "is a community
 * moderator" flag once and reuses it for every node, rather than checking
 * per-committee. Actions without permission are hidden, not disabled.
 */
uses(RefreshDatabase::class);

function hasDeleteButton(string $html, string $committeeDn): bool
{
    return str_contains($html, "confirmDeleteCommittee('{$committeeDn}')");
}

test('a committee moderator sees no delete button for their own committee and its descendants', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);
    $this->actingAs($moderator);

    $html = Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->call('toggleChildren', $parent->getDn())
        ->html();

    expect(hasDeleteButton($html, $parent->getDn()))->toBeFalse()
        ->and(hasDeleteButton($html, $child->getDn()))->toBeFalse();
});

test('a plain community member sees no edit link for any committee', function (): void {
    $community = newCommunity();
    TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->html();

    $editUrl = route('committees.edit', ['realm' => $community->getShortCode(), 'ou' => 'fsr']);

    expect($html)->not->toContain('href="'.$editUrl.'"');
});

test('a plain community member sees no delete button for any committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->html();

    expect(hasDeleteButton($html, $committee->getDn()))->toBeFalse();
});

test('a community moderator sees edit links and delete buttons for every committee', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('loadCommittees')
        ->html();

    $editUrl = route('committees.edit', ['realm' => $community->getShortCode(), 'ou' => 'committee-a']);

    expect(hasDeleteButton($html, $committeeA->getDn()))->toBeTrue()
        ->and(hasDeleteButton($html, $committeeB->getDn()))->toBeTrue()
        ->and($html)->toContain('href="'.$editUrl.'"');
});
