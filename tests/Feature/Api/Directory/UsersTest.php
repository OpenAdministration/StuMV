<?php

use App\Ldap\User as LdapUser;
use App\Models\ProfilePicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a registered client can look up a member of its community', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);
    $ldapTarget = LdapUser::findByUsername($target->username);

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}");

    $response->assertOk()->assertJson([
        'uid' => $target->username,
        'name' => $ldapTarget->getFirstAttribute('cn'),
        'given_name' => $ldapTarget->getFirstAttribute('givenName'),
        'family_name' => $ldapTarget->getFirstAttribute('sn'),
        'picture' => null,
    ]);
});

test('the response includes the user\'s course of study (Studiengang)', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);
    LdapUser::findByUsername($target->username)->fill(['description' => 'Informatik'])->save();

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}");

    $response->assertOk()->assertJson(['course' => 'Informatik']);
});

test('a user with a profile picture gets its URL in the response', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    Storage::disk('public')->put('avatars/some-file-id.webp', 'fake-image-contents');
    ProfilePicture::create([
        'user' => $target->username,
        'file_id' => 'some-file-id',
    ]);

    actingAsDirectoryClient($community, ['users']);

    $response = $this->getJson("/api/$uid/users/{$target->username}");

    $response->assertOk()->assertJson([
        'picture' => asset('storage/avatars/some-file-id.webp'),
    ]);
});

test('looking up a user requires its own users scope - committees and groups no longer suffice', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($community, ['committees', 'groups']);

    $this->getJson("/api/$uid/users/{$target->username}")->assertForbidden();
});

test('looking up a user requires authentication', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::member($community);

    $this->getJson("/api/$uid/users/{$target->username}")->assertUnauthorized();
});

test('a client registered for a different community cannot look up this community\'s users', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    $target = TestLdap::member($community);

    actingAsDirectoryClient($otherCommunity, ['users']);

    $this->getJson("/api/$uid/users/{$target->username}")->assertForbidden();
});

test('looking up an unknown username returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/does-not-exist")->assertNotFound();
});

test('looking up a real user who is not a member of this community returns 404', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $otherCommunity = newCommunity();
    $elsewhereUser = TestLdap::member($otherCommunity);

    actingAsDirectoryClient($community, ['users']);

    $this->getJson("/api/$uid/users/{$elsewhereUser->username}")->assertNotFound();
});
