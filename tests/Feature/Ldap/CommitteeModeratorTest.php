<?php

use App\Ldap\Committee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

/**
 * A committee's hidden moderators group grants "act like a general moderator,
 * but only for this committee and its descendants" - Committee::hasModerator()
 * is the check that walks a target committee up through its ancestors,
 * stopping as soon as it finds one whose moderators group contains the user.
 */
uses(RefreshDatabase::class);

test('moderatorsGroup self-heals - it is created on first access and reused afterwards', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');

    $first = $committee->moderatorsGroup();
    $second = $committee->moderatorsGroup();

    expect($first->getDn())->toBe('cn=moderators,'.$committee->getDn())
        ->and($second->getDn())->toBe($first->getDn());
});

test('a user directly in a committee moderators group is its moderator', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    expect($committee->hasModerator($moderator))->toBeTrue();
});

test('a moderator of a parent committee is also a moderator of its children', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $grandchild = TestLdap::makeCommittee($community, 'grandchild', parentDn: $child->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);

    expect($child->hasModerator($moderator))->toBeTrue()
        ->and($grandchild->hasModerator($moderator))->toBeTrue();
});

test('a moderator of a child committee is not a moderator of its parent', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($child, $community);

    expect($parent->hasModerator($moderator))->toBeFalse();
});

test('a moderator of one committee is not a moderator of an unrelated sibling committee', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    $moderator = TestLdap::committeeModerator($committeeA, $community);

    expect($committeeB->hasModerator($moderator))->toBeFalse();
});

test('a plain community member is not a committee moderator', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);

    expect($committee->hasModerator($member))->toBeFalse();
});

test('the hidden moderators group is excluded from the committee roles list', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $committee->moderatorsGroup(); // force-create it

    $roleCns = $committee->roles()->get()->map(fn ($role) => $role->getFirstAttribute('cn'));

    expect($roleCns)->toContain('mitglied')
        ->not->toContain('moderators');
});
