<?php

use App\Models\ProfilePicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can list the members holding a role in a committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied");

    $response->assertOk()->assertJsonFragment([
        'name' => $member->getFirstAttribute('cn'),
        'course' => $member->getFirstAttribute('description'),
        'picture' => null,
    ]);
});

test('members are the union across multiple committees and roles', function (): void {
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

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&committees[]=stura&roles[]=mitglied&roles[]=vorsitz");

    $names = collect($response->assertOk()->json())->pluck('name');

    expect($names)->toContain($fsrMember->getFirstAttribute('cn'), $sturaMember->getFirstAttribute('cn'));
});

test('a person holding multiple matching roles is only listed once', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $committee = TestLdap::makeCommittee($community, 'fsr');
    $roleA = TestLdap::makeRole($committee, 'mitglied');
    $roleB = TestLdap::makeRole($committee, 'vorsitz');
    $member = TestLdap::makeUser();
    TestLdap::attach($roleA, $member);
    TestLdap::attach($roleB, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied&roles[]=vorsitz");

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('unknown committee and role names are silently ignored', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::makeUser();
    TestLdap::attach($role, $member);

    actingAsDirectoryClient($community, ['committees']);

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&committees[]=unknown&roles[]=mitglied&roles[]=unknown");

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
});

test('requesting members without any committee filter returns 422', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/members?roles[]=mitglied")->assertStatus(422);
});

test('requesting members without any role filter returns 422', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['committees']);

    $this->getJson("/api/$uid/members?committees[]=fsr")->assertStatus(422);
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

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied");

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

    $response = $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied");

    $response->assertOk()->assertJsonFragment([
        'picture' => asset('storage/avatars/some-file-id.webp'),
    ]);
});

test('requesting members requires the committees scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($community, ['groups']);

    $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied")->assertForbidden();
});

test('requesting members requires authentication', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied")->assertUnauthorized();
});

test('a normal delegated end-user token is rejected, even with the right scope', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $user = TestLdap::member($community);

    Passport::actingAs($user, ['committees']);

    $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied")->assertUnauthorized();
});

test('a client registered for a different community cannot request this community\'s members', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    TestLdap::makeCommittee($community, 'fsr');

    actingAsDirectoryClient($otherCommunity, ['committees']);

    $this->getJson("/api/$uid/members?committees[]=fsr&roles[]=mitglied")->assertForbidden();
});
