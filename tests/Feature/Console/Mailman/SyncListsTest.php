<?php

use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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

test('mailman:sync-lists fails fast when MAILMAN_API_URL is not configured', function (): void {
    config(['services.mailman.api_url' => null]);

    $this->artisan('mailman:sync-lists')->assertExitCode(1);
});

test('mailman:sync-lists can be limited to a single mapping via group_dn', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $groupA = TestLdap::makeGroup($community, 'grp-a');
    $groupB = TestLdap::makeGroup($community, 'grp-b');
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'a.lists.example.org'], [$groupA->getDn()]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'b.lists.example.org'], [$groupB->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/a.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    $this->artisan('mailman:sync-lists', ['group_dn' => $groupA->getDn()])->assertExitCode(0);

    Http::assertSentCount(1);
});

test('mailman:sync-lists can be limited to a single list via --list', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'grp-a');
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'a.lists.example.org'], [$group->getDn()]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'b.lists.example.org'], [$group->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/b.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    $this->artisan('mailman:sync-lists', ['--list' => 'b.lists.example.org'])->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/lists/b.lists.example.org/'));
});

test('mailman:sync-lists leaves a list untouched when its group no longer exists', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], ['cn=gone,ou=Groups,ou='.$uid.',ou=Communities,dc=stumv,dc=de']);

    Http::fake();

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertNothingSent();
});

test('mailman:sync-lists can be limited to a single realm, leaving other realms untouched', function (): void {
    $communityA = newCommunity();
    $communityB = newCommunity();
    $groupA = TestLdap::makeGroup($communityA, 'grp-a');
    $groupB = TestLdap::makeGroup($communityB, 'grp-b');
    GroupMailmanList::createForGroups(['realm' => $communityA->getShortCode(), 'mailman_list_id' => 'a.lists.example.org'], [$groupA->getDn()]);
    GroupMailmanList::createForGroups(['realm' => $communityB->getShortCode(), 'mailman_list_id' => 'b.lists.example.org'], [$groupB->getDn()]);

    Http::fake([
        'mailman.test/3.1/lists/a.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    $this->artisan('mailman:sync-lists', ['realm' => $communityA->getShortCode()])->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'a.lists.example.org'));
});

test('mailman:sync-lists logs a failed subscription as an error', function (): void {
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

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 400),
    ]);
    Log::spy();

    $this->artisan('mailman:sync-lists')->assertExitCode(1);

    Log::shouldHaveReceived('error')->with('Mailman subscribe failed', Mockery::on(fn (array $context): bool => $context['email'] === $member->ldap()->getFirstAttribute('mail')));
});

test('mailman:sync-lists skips a disabled list without asking Mailman', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::createForGroups(['realm' => $community->getShortCode(), 'mailman_list_id' => 'newsletter.lists.example.org', 'enabled' => false], [$group->getDn()]);
    Http::fake();

    $this->artisan('mailman:sync-lists')
        ->expectsOutputToContain('newsletter.lists.example.org is disabled, skipping')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

test('mailman:sync-lists keeps syncing other lists when one roster cannot be fetched', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => today()->subMonth()]);
    foreach (['broken', 'working'] as $name) {
        $group = TestLdap::makeGroup($community, $name);
        GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
        GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => "{$name}.lists.example.org"], [$group->getDn()]);
    }

    Http::fake([
        'mailman.test/3.1/lists/broken.lists.example.org/roster/member' => Http::response(status: 500),
        'mailman.test/3.1/lists/working.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    $this->artisan('mailman:sync-lists')
        ->expectsOutputToContain("could not fetch the list's members")
        ->assertExitCode(1);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'working.lists.example.org');
    Http::assertNotSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['list_id'] === 'broken.lists.example.org');
});

test('mailman:sync-lists warns about a member missing from LDAP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $ghost = TestLdap::member($community);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $ghost->username, 'from' => today()->subMonth()]);
    $ghost->ldap()->delete();
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    Http::fake(['mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []])]);

    $this->artisan('mailman:sync-lists')
        ->expectsOutputToContain("Unknown LDAP user: {$ghost->username}")
        ->assertExitCode(0);
});

test('mailman:sync-lists syncs moderators from the moderator groups', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $boardRole = TestLdap::makeRole($committee, 'vorstand');
    $member = TestLdap::member($community);
    $moderator = TestLdap::member($community);
    $members = TestLdap::makeGroup($community, 'newsletter');
    $board = TestLdap::makeGroup($community, 'board');
    GroupMembership::create(['group_dn' => $members->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $board->getDn(), 'role_dn' => $boardRole->getDn()]);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => today()->subMonth()]);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'vorstand', 'committee_dn' => $committee->getDn(), 'username' => $moderator->username, 'from' => today()->subMonth()]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$members->getDn()], [$board->getDn()]);
    $memberMail = $member->ldap()->getFirstAttribute('mail');
    $moderatorMail = $moderator->ldap()->getFirstAttribute('mail');

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => [['email' => $memberMail, 'self_link' => 'http://mailman.test/3.1/members/1']]]),
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/moderator' => Http::response(['entries' => [['email' => 'old-mod@example.org', 'self_link' => 'http://mailman.test/3.1/members/2']]]),
        'mailman.test/3.1/members' => Http::response('', 201),
        'mailman.test/3.1/members/2' => Http::response('', 204),
    ]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members'
        && $request['role'] === 'moderator'
        && $request['subscriber'] === $moderatorMail);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->url() === 'http://mailman.test/3.1/members/2');
    Http::assertNotSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members' && $request['role'] === 'member');
});

test('mailman:sync-lists leaves moderators alone when no moderator group is assigned', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    Http::fake(['mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []])]);

    $this->artisan('mailman:sync-lists')->assertExitCode(0);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'roster/moderator'));
});
