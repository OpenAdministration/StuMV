<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can list the roles a user currently holds', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $target = TestLdap::member($community);
    TestLdap::attach($role, $target->ldap());

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/roles");

    $response->assertOk()->assertExactJson([
        ['ou' => 'fsr', 'cn' => 'mitglied'],
    ]);
});

test('a user with no roles gets an empty list, not an error', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/roles");

    $response->assertOk()->assertExactJson([]);
});

test('listing a user\'s roles requires the users scope - committees and groups no longer suffice', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['committees', 'groups']);

    $this->getJson("/api/$uid/users/{$target->username}/roles")->assertForbidden();
});

test('listing roles of an unknown username returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/does-not-exist/roles")->assertNotFound();
});

test('a registered client can list the committees a user currently has a role in', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $target = TestLdap::member($community);
    TestLdap::attach($role, $target->ldap());

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/committees");

    $response->assertOk()->assertExactJson(['fsr']);
});

test('a user with roles in the same committee only lists it once', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $roleA = TestLdap::makeRole($committee, 'mitglied');
    $roleB = TestLdap::makeRole($committee, 'vorsitz');
    $target = TestLdap::member($community);
    TestLdap::attach($roleA, $target->ldap());
    TestLdap::attach($roleB, $target->ldap());

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/committees");

    $response->assertOk()->assertExactJson(['fsr']);
});

test('a user with no committee roles gets an empty list, not an error', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/committees");

    $response->assertOk()->assertExactJson([]);
});

test('listing a user\'s committees requires the users scope - committees and groups no longer suffice', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['committees', 'groups']);

    $this->getJson("/api/$uid/users/{$target->username}/committees")->assertForbidden();
});

test('listing committees of an unknown username returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/does-not-exist/committees")->assertNotFound();
});

test('a registered client can list the groups of a user', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $target = TestLdap::member($community);
    TestLdap::attach($group, $target->ldap());

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/groups");

    $response->assertOk()->assertExactJson(['newsletter']);
});

test('a user with no groups gets an empty list, not an error', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}/groups");

    $response->assertOk()->assertExactJson([]);
});

test('listing a user\'s groups requires the users scope - committees and groups no longer suffice', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['committees', 'groups']);

    $this->getJson("/api/$uid/users/{$target->username}/groups")->assertForbidden();
});

test('listing groups of an unknown username returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/does-not-exist/groups")->assertNotFound();
});

test('listing roles/committees/groups of a user who is not a member of this community returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    $elsewhereUser = TestLdap::member($otherCommunity);

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/{$elsewhereUser->username}/roles")->assertNotFound();
    $this->getJson("/api/$uid/users/{$elsewhereUser->username}/committees")->assertNotFound();
    $this->getJson("/api/$uid/users/{$elsewhereUser->username}/groups")->assertNotFound();
});

test('a client registered for a different community cannot query this community\'s users', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($otherCommunity, ['users']);

    $this->getJson("/api/$uid/users/{$target->username}/roles")->assertForbidden();
    $this->getJson("/api/$uid/users/{$target->username}/committees")->assertForbidden();
    $this->getJson("/api/$uid/users/{$target->username}/groups")->assertForbidden();
});
