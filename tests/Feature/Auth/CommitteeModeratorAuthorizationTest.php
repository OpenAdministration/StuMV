<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

/**
 * A committee moderator can act like a general moderator for roles and role
 * memberships within the committee they were assigned to (plus its
 * descendants) - see App\Ldap\Committee::hasModerator() and the
 * CommunityModerator middleware, which resolves the {ou} route parameter
 * into a committee-scoped check for those actions. Committees themselves
 * (create/edit/delete) are NOT delegable this way - only a community
 * moderator can manage committees, see CommitteePolicy::edit()/delete()/
 * create() and routes/web.php's plain ->can('moderator', 'uid') gate.
 */
uses(RefreshDatabase::class);

test('a committee moderator cannot edit their own committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    $this->actingAs($moderator)
        ->get(route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']))
        ->assertStatus(403);
});

test('a committee moderator cannot edit a descendant committee', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);

    $this->actingAs($moderator)
        ->get(route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'child']))
        ->assertStatus(403);
});

test('a committee moderator cannot create a new committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    $this->actingAs($moderator)
        ->get(route('committees.new', ['uid' => $community->getShortCode()]))
        ->assertStatus(403);
});

test('a community moderator can still edit and create committees', function (): void {
    $community = newCommunity();
    TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    $this->get(route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']))
        ->assertStatus(200);
    $this->get(route('committees.new', ['uid' => $community->getShortCode()]))
        ->assertStatus(200);
});

test('a plain community member cannot edit any committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);

    $this->actingAs($member)
        ->get(route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']))
        ->assertStatus(403);
});

test('a committee moderator can create a new role in their own committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    $this->actingAs($moderator)
        ->get(route('committees.roles.new', ['uid' => $community->getShortCode(), 'ou' => 'fsr']))
        ->assertStatus(200);
});

test('a committee moderator can create a new role in a descendant committee', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($parent, $community);

    $this->actingAs($moderator)
        ->get(route('committees.roles.new', ['uid' => $community->getShortCode(), 'ou' => 'child']))
        ->assertStatus(200);
});

test('a committee moderator cannot create a role in an unrelated committee', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    $moderator = TestLdap::committeeModerator($committeeA, $community);

    $this->actingAs($moderator)
        ->get(route('committees.roles.new', ['uid' => $community->getShortCode(), 'ou' => 'committee-b']))
        ->assertStatus(403);
});

test('a committee moderator cannot create a role in their committee\'s own parent', function (): void {
    $community = newCommunity();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());
    $moderator = TestLdap::committeeModerator($child, $community);

    $this->actingAs($moderator)
        ->get(route('committees.roles.new', ['uid' => $community->getShortCode(), 'ou' => 'parent']))
        ->assertStatus(403);
});

test('a committee moderator does not gain community-wide tools access', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    $this->actingAs($moderator)
        ->get(route('tools.dashboard', ['uid' => $community->getShortCode()]))
        ->assertStatus(403);
});

test('a committee moderator cannot add or remove community-wide moderators', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);

    $this->actingAs($moderator)
        ->get(route('realms.mods.new', ['uid' => $community->getShortCode()]))
        ->assertStatus(403);
});
