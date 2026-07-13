<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can list the committees of its community', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees");

    $response->assertOk()->assertJsonFragment(['ou' => 'fsr']);
});

test('listing committees requires the committees scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['groups']);

    $this->getJson("/api/$uid/committees")->assertForbidden();
});

test('listing committees requires authentication', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->getJson("/api/$uid/committees")->assertUnauthorized();
});

test('a normal delegated end-user token is rejected, even with the right scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);

    Passport::actingAs($user, ['committees']);

    $this->getJson("/api/$uid/committees")->assertUnauthorized();
});

test('a client registered for a different community cannot list this community\'s committees', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();

    actingAsDirectoryClient($otherCommunity, ['committees']);

    $this->getJson("/api/$uid/committees")->assertForbidden();
});

test('a registered client can list the roles of a committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr/roles");

    $response->assertOk()->assertJsonFragment(['cn' => 'mitglied']);
});

test('a registered client can list the members of a role', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr/roles/mitglied/members");

    $response->assertOk()->assertExactJson([$member->getFirstAttribute('uid')]);
});

test('requesting members of an unknown role returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/committees/fsr/roles/unknown/members")->assertNotFound();
});
