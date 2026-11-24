<?php

use App\Jobs\SyncPeopleToMailmanLists;
use App\Livewire\Committee\AddUserToRole;
use App\Livewire\Committee\ListRoleMembers;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use App\Support\RoleMembershipLdapSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function membershipFor(string $realm, string $committeeDn, string $roleCn, string $username, array $dates = []): RoleMembership
{
    return RoleMembership::create([
        'realm' => $realm,
        'role_cn' => $roleCn,
        'committee_dn' => $committeeDn,
        'username' => $username,
        'from' => $dates['from'] ?? today()->subMonth(),
        'until' => $dates['until'] ?? null,
    ]);
}

test('adding a person to a role puts them into the role and its groups in LDAP', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $member = TestLdap::member($community);
    actingAsModerator($community);

    Livewire::test(AddUserToRole::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', [$member->username])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $dn = $member->ldap()->getDn();
    expect($role->fresh()->getAttribute('uniqueMember'))->toContain($dn)
        ->and($group->fresh()->getAttribute('uniqueMember'))->toContain($dn);
});

test('a membership starting in the future is not written to LDAP', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);

    $membership = membershipFor($community->getShortCode(), $committee->getDn(), 'mitglied', $member->username, ['from' => today()->addDay()]);
    resolve(RoleMembershipLdapSync::class)->sync($membership);

    expect($role->fresh()->getAttribute('uniqueMember'))->not->toContain($member->ldap()->getDn());
});

test('a membership that already ended is not written to LDAP', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);

    $membership = membershipFor($community->getShortCode(), $committee->getDn(), 'mitglied', $member->username, ['from' => today()->subYear(), 'until' => today()->subDay()]);
    resolve(RoleMembershipLdapSync::class)->sync($membership);

    expect($role->fresh()->getAttribute('uniqueMember'))->not->toContain($member->ldap()->getDn());
});

test('removing a person from a role removes them from the role and its groups in LDAP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $member = TestLdap::member($community);
    $membership = membershipFor($uid, $committee->getDn(), 'mitglied', $member->username);
    resolve(RoleMembershipLdapSync::class)->sync($membership);
    actingAsModerator($community);

    Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->call('prepareDeletion', $membership->id)
        ->assertOk()
        ->call('commitDeletion');

    expect($role->fresh()->getAttribute('uniqueMember'))->toBe([''])
        ->and($group->fresh()->getAttribute('uniqueMember'))->toBe(['']);
});

test('ending a membership keeps the person in a group another current role still grants', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $leaving = TestLdap::makeRole($committee, 'mitglied');
    $staying = TestLdap::makeRole($committee, 'vorsitz');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $leaving->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $staying->getDn()]);
    $member = TestLdap::member($community);
    $sync = resolve(RoleMembershipLdapSync::class);
    $sync->sync(membershipFor($uid, $committee->getDn(), 'vorsitz', $member->username));
    $membership = membershipFor($uid, $committee->getDn(), 'mitglied', $member->username);
    $sync->sync($membership);

    $membership->update(['until' => today()->subDay()]);
    $sync->sync($membership, wasCurrent: true);

    $dn = $member->ldap()->getDn();
    expect($leaving->fresh()->getAttribute('uniqueMember'))->not->toContain($dn)
        ->and($staying->fresh()->getAttribute('uniqueMember'))->toContain($dn)
        ->and($group->fresh()->getAttribute('uniqueMember'))->toContain($dn);
});

test('mailing lists are only updated when Mailman is configured', function (): void {
    Bus::fake();
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $member = TestLdap::member($community);
    $sync = resolve(RoleMembershipLdapSync::class);

    config(['services.mailman.api_url' => null]);
    $sync->sync(membershipFor($community->getShortCode(), $committee->getDn(), 'mitglied', $member->username));
    $sync->syncMailmanLists();
    Bus::assertNothingDispatched();

    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $sync->sync(membershipFor($community->getShortCode(), $committee->getDn(), 'mitglied', $member->username));
    $sync->syncMailmanLists();
    Bus::assertDispatchedSync(SyncPeopleToMailmanLists::class);
});

test('adding several people at once updates LDAP for each and mailing lists in one go', function (): void {
    Bus::fake();
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $first = TestLdap::member($community);
    $second = TestLdap::member($community);
    actingAsModerator($community);

    Livewire::test(AddUserToRole::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', [$first->username, $second->username])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();
    resolve(RoleMembershipLdapSync::class)->syncMailmanLists();

    foreach ([$first, $second] as $member) {
        expect($role->fresh()->getAttribute('uniqueMember'))->toContain($member->ldap()->getDn())
            ->and($group->fresh()->getAttribute('uniqueMember'))->toContain($member->ldap()->getDn());
    }
    Bus::assertDispatchedSyncTimes(SyncPeopleToMailmanLists::class, 1);
    Bus::assertDispatchedSync(SyncPeopleToMailmanLists::class, fn (SyncPeopleToMailmanLists $job): bool => $job->usernames === [$first->username, $second->username]);
});

test('several people are subscribed with a single roster fetch per list', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $first = TestLdap::member($community);
    $second = TestLdap::member($community);
    membershipFor($uid, $committee->getDn(), 'mitglied', $first->username);
    membershipFor($uid, $committee->getDn(), 'mitglied', $second->username);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'active.lists.example.org'], [$group->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/active.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    dispatch_sync(new SyncPeopleToMailmanLists($uid, [$first->username, $second->username], [$group->getDn()]));

    Http::assertSentCount(3);
    foreach ([$first, $second] as $member) {
        Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
            && $request['subscriber'] === $member->ldap()->getFirstAttribute('mail'));
    }
});

test('the person is subscribed to the mailing lists of their groups', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'active');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $member = TestLdap::member($community);
    membershipFor($uid, $committee->getDn(), 'mitglied', $member->username);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'active.lists.example.org'], [$group->getDn()]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'off.lists.example.org', 'enabled' => false], [$group->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/active.lists.example.org/roster/member' => Http::response(['entries' => [
            ['email' => 'someone-else@example.test', 'self_link' => 'http://mailman.test/3.1/members/1'],
        ]]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    dispatch_sync(new SyncPeopleToMailmanLists($uid, [$member->username], [$group->getDn()]));

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'active.lists.example.org'
        && $request['subscriber'] === $member->ldap()->getFirstAttribute('mail'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'off.lists.example.org') || $request->method() === 'DELETE');
});

test('the person is unsubscribed once no current role feeds the list', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'active');
    $member = TestLdap::member($community);
    $mail = $member->ldap()->getFirstAttribute('mail');
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'active.lists.example.org'], [$group->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/active.lists.example.org/roster/member' => Http::response(['entries' => [
            ['email' => $mail, 'self_link' => 'http://mailman.test/3.1/members/7'],
            ['email' => 'someone-else@example.test', 'self_link' => 'http://mailman.test/3.1/members/1'],
        ]]),
        'mailman.test/3.1/members/7' => Http::response('', 204),
    ]);

    dispatch_sync(new SyncPeopleToMailmanLists($uid, [$member->username], [$group->getDn()]));

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members/7' && $request->method() === 'DELETE');
    Http::assertNotSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members/1');
});
