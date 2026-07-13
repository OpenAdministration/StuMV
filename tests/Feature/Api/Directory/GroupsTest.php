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
