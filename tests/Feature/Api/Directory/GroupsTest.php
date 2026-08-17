<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can list the groups of its community', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'lecturers');

    actingAsDirectoryClient($community, ['groups']);

    $response = $this->getJson("/api/$uid/groups");

    $response->assertOk()->assertJsonFragment(['cn' => 'lecturers']);
});

test('listing groups requires the groups scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/groups")->assertForbidden();
});

test('listing groups requires authentication', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->getJson("/api/$uid/groups")->assertUnauthorized();
});

test('a client registered for a different community cannot list this community\'s groups', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    TestLdap::makeGroup($community, 'lecturers');

    actingAsDirectoryClient($otherCommunity, ['groups']);

    $this->getJson("/api/$uid/groups")->assertForbidden();
});

test('a registered client can look up a single group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'lecturers');
    $group->fill(['description' => 'Lecturers'])->save();

    actingAsDirectoryClient($community, ['groups']);

    $response = $this->getJson("/api/$uid/groups/lecturers");

    $response->assertOk()->assertExactJson(['cn' => 'lecturers', 'description' => 'Lecturers']);
});

test('looking up an unknown group returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['groups']);

    $this->getJson("/api/$uid/groups/unknown")->assertNotFound();
});

test('a registered client can list the members of a group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'lecturers');
    $member = TestLdap::makeUser();
    TestLdap::attach($group, $member);

    actingAsDirectoryClient($community, ['groups']);

    $response = $this->getJson("/api/$uid/groups/lecturers/members");

    $response->assertOk()->assertExactJson([$member->getFirstAttribute('uid')]);
});

test('requesting members of an unknown group returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['groups']);

    $this->getJson("/api/$uid/groups/unknown/members")->assertNotFound();
});

test('groups are sorted alphabetically by cn', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'zzz');
    TestLdap::makeGroup($community, 'aaa');

    actingAsDirectoryClient($community, ['groups']);

    $response = $this->getJson("/api/$uid/groups");

    expect(collect($response->assertOk()->json())->pluck('cn')->all())->toBe(['aaa', 'zzz']);
});

test('group members are sorted alphabetically by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'lecturers');

    $zeta = TestLdap::makeUser();
    $zeta->fill(['cn' => 'Zeta Person'])->save();
    TestLdap::attach($group, $zeta);

    $alpha = TestLdap::makeUser();
    $alpha->fill(['cn' => 'Alpha Person'])->save();
    TestLdap::attach($group, $alpha);

    actingAsDirectoryClient($community, ['groups']);

    $response = $this->getJson("/api/$uid/groups/lecturers/members");

    expect($response->assertOk()->json())->toBe([
        $alpha->getFirstAttribute('uid'),
        $zeta->getFirstAttribute('uid'),
    ]);
});
