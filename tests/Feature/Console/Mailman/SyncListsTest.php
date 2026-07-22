<?php

use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.mailman.url' => 'http://mailman.test/3.1']);
});

test('mailman:sync-lists subscribes an active member missing from the Mailman roster', function (): void {
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

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'newsletter.lists.example.org'
        && $request['subscriber'] === $member->ldap()->getFirstAttribute('mail')
        && $request['pre_approved'] === 'true');
});

test('mailman:sync-lists unsubscribes a Mailman member no longer active in the DB', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response([
            'entries' => [
                ['email' => 'stale@example.com', 'self_link' => 'http://mailman.test/3.1/members/42'],
            ],
        ]),
        'mailman.test/3.1/members/42' => Http::response('', 204),
    ]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members/42' && $request->method() === 'DELETE');
});

test('mailman:sync-lists leaves an already-correct member untouched', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $member = TestLdap::member($community);
    $email = $member->ldap()->getFirstAttribute('mail');

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response([
            'entries' => [
                ['email' => $email, 'self_link' => 'http://mailman.test/3.1/members/1'],
            ],
        ]),
    ]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertNotSent(fn ($request): bool => $request->method() === 'POST' || $request->method() === 'DELETE');
});

test('mailman:sync-lists does nothing without any mapping', function (): void {
    Http::fake();

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertNothingSent();
});

test('mailman:sync-lists fails fast when MAILMAN_URL is not configured', function (): void {
    config(['services.mailman.url' => null]);

    $this->artisan('mailman:sync-lists')->assertExitCode(1);
});

test('mailman:sync-lists can be limited to a single mapping via group_dn', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $groupA = TestLdap::makeGroup($community, 'grp-a');
    $groupB = TestLdap::makeGroup($community, 'grp-b');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupA->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupB->getDn(), 'mailman_list_id' => 'b.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/a.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    $this->artisan('mailman:sync-lists', ['group_dn' => $groupA->getDn()])->assertExitCode(0);

    Http::assertSentCount(1);
});

test('mailman:sync-lists unions members of several groups mapped to the same list', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $groupA = TestLdap::makeGroup($community, 'grp-a');
    $groupB = TestLdap::makeGroup($community, 'grp-b');
    $memberA = TestLdap::member($community);
    $memberB = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $groupA->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $groupB->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $memberA->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $memberB->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupA->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupB->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'newsletter.lists.example.org'
        && $request['subscriber'] === $memberA->ldap()->getFirstAttribute('mail'));
    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'newsletter.lists.example.org'
        && $request['subscriber'] === $memberB->ldap()->getFirstAttribute('mail'));
});

test('mailman:sync-lists can be limited to a single realm, leaving other realms untouched', function (): void {
    $communityA = newCommunity();
    $communityB = newCommunity();
    $groupA = TestLdap::makeGroup($communityA, 'grp-a');
    $groupB = TestLdap::makeGroup($communityB, 'grp-b');
    GroupMailmanList::create(['realm' => $communityA->getShortCode(), 'group_dn' => $groupA->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    GroupMailmanList::create(['realm' => $communityB->getShortCode(), 'group_dn' => $groupB->getDn(), 'mailman_list_id' => 'b.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/a.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    $this->artisan('mailman:sync-lists', ['realm' => $communityA->getShortCode()])->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'a.lists.example.org'));
});
