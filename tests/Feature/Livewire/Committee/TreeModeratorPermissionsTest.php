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
 * per-committee.
 */
uses(RefreshDatabase::class);

function deleteButtonIsDisabled(string $html, string $committeeDn): bool
{
    $marker = "confirmDeleteCommittee('{$committeeDn}')";
    $pos = strpos($html, $marker);
    expect($pos)->not->toBeFalse();

    return str_contains(substr($html, $pos, strlen($marker) + 30), 'disabled="disabled"');
}

test('a committee moderator sees a disabled delete button for their own committee and its descendants', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);
    $this->actingAs($moderator);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->call('toggleChildren', $parent->getDn())
        ->html();

    expect(deleteButtonIsDisabled($html, $parent->getDn()))->toBeTrue()
        ->and(deleteButtonIsDisabled($html, $child->getDn()))->toBeTrue();
});

test('a plain community member sees disabled delete buttons for every committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    expect(deleteButtonIsDisabled($html, $committee->getDn()))->toBeTrue();
});

test('a community moderator sees enabled delete buttons for every committee', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    actingAsModerator($community);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    expect(deleteButtonIsDisabled($html, $committeeA->getDn()))->toBeFalse()
        ->and(deleteButtonIsDisabled($html, $committeeB->getDn()))->toBeFalse();
});
