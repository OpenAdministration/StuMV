<?php

use App\Livewire\Committee\ListCommitteesTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * The tree computes each node's "can edit/delete this committee" flag once
 * per node and threads it down to descendants (see
 * ListCommitteesTree::buildNode()), instead of re-deriving it via
 * Committee::hasModerator()'s ancestor walk for every node - these lock in
 * that the resulting per-node permission is still correct end-to-end through
 * the rendered menu, not just at the policy layer (already covered by
 * CommitteeModeratorAuthorizationTest and CommitteeModeratorTest).
 */
uses(RefreshDatabase::class);

function deleteButtonIsDisabled(string $html, string $committeeDn): bool
{
    $marker = "confirmDeleteCommittee('{$committeeDn}')";
    $pos = strpos($html, $marker);
    expect($pos)->not->toBeFalse();

    return str_contains(substr($html, $pos, strlen($marker) + 30), 'disabled="disabled"');
}

test('a committee moderator sees an enabled delete button for a nested descendant of their committee', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);
    $this->actingAs($moderator);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->call('toggleChildren', $parent->getDn())
        ->html();

    expect(deleteButtonIsDisabled($html, $parent->getDn()))->toBeFalse()
        ->and(deleteButtonIsDisabled($html, $child->getDn()))->toBeFalse();
});

test('a committee moderator sees a disabled delete button for an unrelated committee', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    $moderator = TestLdap::committeeModerator($committeeA, $community);
    $this->actingAs($moderator);

    $html = Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('loadCommittees')
        ->html();

    expect(deleteButtonIsDisabled($html, $committeeA->getDn()))->toBeFalse()
        ->and(deleteButtonIsDisabled($html, $committeeB->getDn()))->toBeTrue();
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
