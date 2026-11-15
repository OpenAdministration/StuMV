<?php

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewRealm;
use App\Livewire\Settings\Domain\NewDomain;
use App\Livewire\Settings\EditRealm;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\Invitation;
use App\Models\RoleMembership;
use App\Models\User;
use App\Models\UserAdditionalEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a super admin can create a realm with the full skeleton', function (): void {
    actingAsSuperAdmin();
    $uid = 'trealm'.bin2hex(random_bytes(3));

    Livewire::test(NewRealm::class)
        ->set('uid', $uid)
        ->set('name', 'Test Realm '.$uid)
        ->call('save')
        ->assertHasNoErrors();

    $community = Community::findByUid($uid);
    TestLdap::track($community); // hand the component-created community to teardown

    expect($community)->not->toBeNull()
        ->and(OrganizationalUnit::query()->find($community->peopleDn()))->not->toBeNull()
        ->and($community->adminsGroup())->not->toBeNull();
});

test('an admin can rename their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->set('name', 'Renamed Realm')
        ->call('save')
        ->assertHasNoErrors();

    expect(Community::findByUid($uid)->getFirstAttribute('description'))->toBe('Renamed Realm');
});

test('a super admin can delete a realm once the shortcode is confirmed', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', $uid)
        ->call('deleteCommit');

    expect(Community::findByUid($uid))->toBeNull();
});

test('deleting a realm also purges its DB-side data', function (): void {
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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    $invitation = Invitation::create(['realm' => $uid, 'email' => 'invited@example.test', 'expires_at' => now()->addDays(7)]);
    UserAdditionalEmail::create(['username' => $member->username, 'realm' => $uid, 'address' => 'alias@example.test']);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('App', ['https://app.example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();
    $token = Token::create([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'client_id' => $client->id,
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
    RefreshToken::create(['id' => Str::random(80), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDay()]);
    DB::table('oauth_device_codes')->insert([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'client_id' => $client->id,
        'user_code' => 'ABCD1234',
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', $uid)
        ->call('deleteCommit');

    expect(Community::findByUid($uid))->toBeNull()
        ->and(RoleMembership::where('realm', $uid)->exists())->toBeFalse()
        ->and(GroupMembership::where('group_dn', $group->getDn())->exists())->toBeFalse()
        ->and(GroupMailmanList::where('realm', $uid)->exists())->toBeFalse()
        ->and(Invitation::find($invitation->id))->toBeNull()
        ->and(DB::table('invitation_role_selections')->where('invitation_id', $invitation->id)->exists())->toBeFalse()
        ->and(UserAdditionalEmail::where('realm', $uid)->exists())->toBeFalse()
        ->and(User::find($member->id))->toBeNull()
        ->and(DB::table('oauth_clients')->where('id', $client->id)->exists())->toBeFalse()
        ->and(Token::find($token->id))->toBeNull()
        ->and(RefreshToken::where('access_token_id', $token->id)->exists())->toBeFalse()
        ->and(DB::table('oauth_device_codes')->where('client_id', $client->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
});

test('the admin realm cannot be deleted', function (): void {
    $superAdmin = actingAsSuperAdmin();
    $adminRealm = new Community(['ou' => Community::ADMIN_REALM_UID]);

    expect($superAdmin->can('delete', $adminRealm))->toBeFalse();
});

test('deleting a realm is rejected if the typed shortcode does not match', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', 'wrong-code')
        ->call('deleteCommit')
        ->assertHasErrors('deleteConfirmText');

    expect(Community::findByUid($uid))->not->toBeNull();
});

test('an admin can add a domain to their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);
    $dc = 'dom'.bin2hex(random_bytes(3)).'.de';

    Livewire::test(NewDomain::class, ['realm' => $community])
        ->set('dc', $dc)
        ->call('save')
        ->assertHasNoErrors();

    expect(Domain::findBy('dc', $dc))->not->toBeNull();
});
