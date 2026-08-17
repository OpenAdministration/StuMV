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

test('a registered client can look up a single committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $committee->fill(['description' => 'Fachschaftsrat'])->save();

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr");

    $response->assertOk()->assertExactJson(['ou' => 'fsr', 'description' => 'Fachschaftsrat']);
});

test('looking up an unknown committee returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/committees/unknown")->assertNotFound();
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

test('a registered client can look up a single role', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $role->fill(['description' => 'Mitglied'])->save();

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr/roles/mitglied");

    $response->assertOk()->assertExactJson(['cn' => 'mitglied', 'description' => 'Mitglied']);
});

test('looking up an unknown role returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/committees/fsr/roles/unknown")->assertNotFound();
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

test('committees are sorted alphabetically by description', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'zzz')->fill(['description' => 'Zeta committee'])->save();
    TestLdap::makeCommittee($community, 'aaa')->fill(['description' => 'Alpha committee'])->save();

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees");

    expect(collect($response->assertOk()->json())->pluck('ou')->all())->toBe(['aaa', 'zzz']);
});

test('roles of a committee are sorted alphabetically by description', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'zzz')->fill(['description' => 'Zeta role'])->save();
    TestLdap::makeRole($committee, 'aaa')->fill(['description' => 'Alpha role'])->save();

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr/roles");

    expect(collect($response->assertOk()->json())->pluck('cn')->all())->toBe(['aaa', 'zzz']);
});

test('role members are sorted alphabetically by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');

    $zeta = TestLdap::makeUser();
    $zeta->fill(['cn' => 'Zeta Person'])->save();
    TestLdap::attach($role, $zeta);

    $alpha = TestLdap::makeUser();
    $alpha->fill(['cn' => 'Alpha Person'])->save();
    TestLdap::attach($role, $alpha);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/committees/fsr/roles/mitglied/members");

    expect($response->assertOk()->json())->toBe([
        $alpha->getFirstAttribute('uid'),
        $zeta->getFirstAttribute('uid'),
    ]);
});
