<?php

use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\PassportClient;
use App\Models\ProfilePicture;
use App\Models\RealmBranding;
use App\Models\RealmSsoProvider;
use App\Models\RoleMembership;
use App\Models\SsoProviderRoleMapping;
use App\Models\User;
use App\Support\RealmDataPurger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('purging a realm removes every DB row and file scoped to it', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $uid = $community->getShortCode();

    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $member = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Storage::disk('public')->put('avatars/pic.webp', 'fake-avatar');
    ProfilePicture::create(['user' => $member->username, 'realm' => $uid, 'file_id' => 'pic']);

    Storage::disk('public')->put('realm-branding/logo.webp', 'fake-logo');
    Storage::disk('public')->put('realm-branding/bg.webp', 'fake-bg');
    RealmBranding::create(['realm' => $uid, 'logo_id' => 'logo.webp', 'background_id' => 'bg.webp']);

    $provider = makeSsoProvider($uid);
    $mapping = $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => 'mitglied',
    ]);

    $client = actingAsDirectoryClient($community);
    $token = $client->tokens()->create([
        'id' => Str::random(80),
        'user_id' => null,
        'name' => 'Test Token',
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);
    $authCode = $client->authCodes()->create([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'scopes' => json_encode([]),
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => 'reset@example.org',
        'realm' => $uid,
        'token' => 'irrelevant',
        'created_at' => now(),
    ]);

    // Untouched control data belonging to a second, unrelated realm - proves
    // the purge is scoped by realm and doesn't just wipe every row.
    $otherCommunity = newCommunity();
    $otherUid = $otherCommunity->getShortCode();
    $otherCommittee = TestLdap::makeCommittee($otherCommunity, 'fsr');
    $otherRole = TestLdap::makeRole($otherCommittee, 'mitglied');
    $otherGroup = TestLdap::makeGroup($otherCommunity, 'newsletter');
    $otherMember = TestLdap::member($otherCommunity);
    GroupMembership::create(['group_dn' => $otherGroup->getDn(), 'role_dn' => $otherRole->getDn()]);
    RoleMembership::create([
        'realm' => $otherUid,
        'role_cn' => 'mitglied',
        'committee_dn' => $otherCommittee->getDn(),
        'username' => $otherMember->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $otherUid, 'group_dn' => $otherGroup->getDn(), 'mailman_list_id' => 'other.lists.example.org']);

    RealmDataPurger::purge($uid);

    expect(RoleMembership::where('realm', $uid)->exists())->toBeFalse()
        ->and(GroupMembership::where('group_dn', $group->getDn())->exists())->toBeFalse()
        ->and(GroupMailmanList::where('realm', $uid)->exists())->toBeFalse()
        ->and(ProfilePicture::where('realm', $uid)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists('avatars/pic.webp'))->toBeFalse()
        ->and(RealmBranding::where('realm', $uid)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists('realm-branding/logo.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists('realm-branding/bg.webp'))->toBeFalse()
        ->and(RealmSsoProvider::find($provider->id))->toBeNull()
        ->and(SsoProviderRoleMapping::find($mapping->id))->toBeNull()
        ->and(PassportClient::find($client->id))->toBeNull()
        ->and($client->tokens()->find($token->id))->toBeNull()
        ->and($client->authCodes()->find($authCode->id))->toBeNull()
        ->and(User::where('realm', $uid)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('realm', $uid)->exists())->toBeFalse();

    expect(RoleMembership::where('realm', $otherUid)->exists())->toBeTrue()
        ->and(GroupMembership::where('group_dn', $otherGroup->getDn())->exists())->toBeTrue()
        ->and(GroupMailmanList::where('realm', $otherUid)->exists())->toBeTrue()
        ->and(User::where('realm', $otherUid)->exists())->toBeTrue();
});

test('purging a realm with no associated data does nothing', function (): void {
    $community = newCommunity();

    RealmDataPurger::purge($community->getShortCode());

    expect(true)->toBeTrue();
});
