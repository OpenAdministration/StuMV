<?php

use App\Models\ProfilePicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can list the members holding a given committee/role pair', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ]);

    $response->assertOk()->assertJsonFragment([
        'name' => $member->getFirstAttribute('cn'),
        'course' => $member->getFirstAttribute('description'),
        'picture' => null,
    ]);
});

test('members are the union across multiple pairs', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $fsr = TestLdap::makeCommittee($community, 'fsr');
    $fsrRole = TestLdap::makeRole($fsr, 'mitglied');
    $fsrMember = TestLdap::makeUser();
    TestLdap::attach($fsrRole, $fsrMember);

    $stura = TestLdap::makeCommittee($community, 'stura');
    $sturaRole = TestLdap::makeRole($stura, 'vorsitz');
    $sturaMember = TestLdap::makeUser();
    TestLdap::attach($sturaRole, $sturaMember);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [
            ['ou' => 'fsr', 'cn' => 'mitglied'],
            ['ou' => 'stura', 'cn' => 'vorsitz'],
        ],
    ]);

    $names = collect($response->assertOk()->json())->pluck('name');

    expect($names)->toContain($fsrMember->getFirstAttribute('cn'), $sturaMember->getFirstAttribute('cn'));
});

test('a committee/role pair only matches that exact combination, not a cross product', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $fsr = TestLdap::makeCommittee($community, 'fsr');
    $fsrRole = TestLdap::makeRole($fsr, 'mitglied');
    $fsrMember = TestLdap::makeUser();
    TestLdap::attach($fsrRole, $fsrMember);

    $stura = TestLdap::makeCommittee($community, 'stura');
    TestLdap::makeRole($stura, 'mitglied');

    actingAsDirectoryClient($community, ['committees']);

    // "stura" has no "vorsitz" role, so this pair should match nobody -
    // only the exact fsr/mitglied pair should contribute a member.
    $response = $this->postJson("/api/$uid/members", [
        'roles' => [
            ['ou' => 'fsr', 'cn' => 'mitglied'],
            ['ou' => 'stura', 'cn' => 'vorsitz'],
        ],
    ]);

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('a person matching multiple pairs is only listed once', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $committee = TestLdap::makeCommittee($community, 'fsr');
    $roleA = TestLdap::makeRole($committee, 'mitglied');
    $roleB = TestLdap::makeRole($committee, 'vorsitz');
    $member = TestLdap::makeUser();
    TestLdap::attach($roleA, $member);
    TestLdap::attach($roleB, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [
            ['ou' => 'fsr', 'cn' => 'mitglied'],
            ['ou' => 'fsr', 'cn' => 'vorsitz'],
        ],
    ]);

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('pairs naming an unknown committee or role are silently ignored', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [
            ['ou' => 'fsr', 'cn' => 'mitglied'],
            ['ou' => 'unknown', 'cn' => 'mitglied'],
            ['ou' => 'fsr', 'cn' => 'unknown'],
        ],
    ]);

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('requesting members without any pairs returns 422', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['committees']);

    $this->postJson("/api/$uid/members", [])->assertStatus(422);
});

test('the response includes the course of study (Studiengang)', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    $member->fill(['description' => 'Informatik'])->save();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ]);

    $response->assertOk()->assertJsonFragment(['course' => 'Informatik']);
});

test('a member with a profile picture gets its URL in the response', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    Storage::disk('public')->put('avatars/some-file-id.webp', 'fake-image-contents');
    ProfilePicture::create([
        'user' => $member->getFirstAttribute('uid'),
        'file_id' => 'some-file-id',
    ]);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ]);

    $response->assertOk()->assertJsonFragment([
        'picture' => asset('storage/avatars/some-file-id.webp'),
    ]);
});

test('requesting members requires the committees scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['groups']);

    $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ])->assertForbidden();
});

test('requesting members requires authentication', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ])->assertUnauthorized();
});

test('a normal delegated end-user token is rejected, even with the right scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);

    Passport::actingAs($user, ['committees']);

    $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ])->assertUnauthorized();
});

test('a client registered for a different community cannot request this community\'s members', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($otherCommunity, ['committees']);

    $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ])->assertForbidden();
});

test('the roles a member holds are omitted by default', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
    ]);

    expect($response->assertOk()->json()[0])->not->toHaveKey('roles');
});

test('include_roles lists which requested role(s) a member holds, with a human-readable name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $role->fill(['description' => 'Mitglied'])->save();
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied']],
        'include_roles' => true,
    ]);

    $response->assertOk()->assertJsonFragment([
        'roles' => [['ou' => 'fsr', 'cn' => 'mitglied', 'role_name' => 'Mitglied']],
    ]);
});

test('include_roles lists every requested role a member holds, deduplicated', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $roleA = TestLdap::makeRole($committee, 'mitglied');
    $roleB = TestLdap::makeRole($committee, 'vorsitz');
    $member = TestLdap::makeUser();
    TestLdap::attach($roleA, $member);
    TestLdap::attach($roleB, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->postJson("/api/$uid/members", [
        'roles' => [
            ['ou' => 'fsr', 'cn' => 'mitglied'],
            ['ou' => 'fsr', 'cn' => 'vorsitz'],
        ],
        'include_roles' => true,
    ]);

    $response->assertOk();
    $roles = collect($response->json()[0]['roles'])->pluck('cn');

    expect($roles)->toHaveCount(2)->and($roles)->toContain('mitglied', 'vorsitz');
});
