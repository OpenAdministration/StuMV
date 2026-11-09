<?php

use App\Livewire\Realm\CommunityDashboard;
use App\Livewire\Realm\LdapSyncStatus;
use App\Livewire\Realm\MailmanSyncStatus;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
});

test('the dashboard shows the sync status cards to admins only', function (): void {
    $community = newCommunity();

    actingAsAdmin($community);
    Livewire::test(CommunityDashboard::class, ['realm' => $community])
        ->assertSeeLivewire(LdapSyncStatus::class)
        ->assertSeeLivewire(MailmanSyncStatus::class);

    actingAsModerator($community);
    Livewire::test(CommunityDashboard::class, ['realm' => $community])
        ->assertDontSeeLivewire(LdapSyncStatus::class)
        ->assertDontSeeLivewire(MailmanSyncStatus::class);
});

test('the mailman status card is hidden when Mailman is not configured', function (): void {
    config(['services.mailman.api_url' => null]);
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(CommunityDashboard::class, ['realm' => $community])
        ->assertSeeLivewire(LdapSyncStatus::class)
        ->assertDontSeeLivewire(MailmanSyncStatus::class);
});

test('the ldap status counts pending member changes', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $role->members()->attach(TestLdap::makeUser());
    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => TestLdap::member($community)->username,
        'from' => today()->subMonth(),
    ]);

    Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $community->getShortCode()])
        ->assertViewHas('state', 'pending')
        ->assertSee(__('sync.status_pending'));
});

test('the ldap status is synced when nothing would change', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $community->getShortCode()])
        ->assertViewHas('state', 'synced');
});

test('the mailman status summarizes enabled lists and skips disabled ones', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $active = TestLdap::member($community);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $active->username, 'from' => today()->subMonth()]);
    foreach (['synced', 'outdated', 'disabled'] as $name) {
        $group = TestLdap::makeGroup($community, $name);
        GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
        GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => "{$name}.lists.example.org", 'enabled' => $name !== 'disabled']);
    }
    $email = $active->ldap()->getFirstAttribute('mail');
    $roster = fn (array $emails) => Http::response(['entries' => array_map(fn (string $e): array => ['email' => $e, 'self_link' => 'http://mailman.test/3.1/members/'.md5($e)], $emails)]);
    Http::fake([
        'mailman.test/3.1/lists/synced.lists.example.org/roster/member' => $roster([$email]),
        'mailman.test/3.1/lists/outdated.lists.example.org/roster/member' => $roster([]),
    ]);
    actingAsAdmin($community);

    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])
        ->assertViewHas('state', 'pending')
        ->assertSee(__('sync.status_pending'));

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'disabled.lists.example.org'));
});

test('a moderator cannot load the status components', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $community->getShortCode()])->assertForbidden();
    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $community->getShortCode()])->assertForbidden();
});

/**
 * @return array{0: string, 1: GroupMailmanList, 2: string}
 */
function cachedStatusSetup(): array
{
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $active = TestLdap::member($community);
    RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $active->username, 'from' => today()->subMonth()]);
    $group = TestLdap::makeGroup($community, 'cached');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $mapping = GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'cached.lists.example.org']);
    actingAsAdmin($community);

    return [$uid, $mapping, $active->ldap()->getFirstAttribute('mail')];
}

/**
 * Later Http::fake() calls don't override earlier ones, so the roster is read by reference.
 *
 * @param  array<int, string>|null  $roster  null makes Mailman fail
 */
function fakeCachedRoster(?array &$roster): void
{
    Http::fake(['mailman.test/3.1/lists/cached.lists.example.org/roster/member' => function () use (&$roster) {
        return $roster === null
            ? Http::response(status: 500)
            : Http::response(['entries' => array_map(fn (string $e): array => ['email' => $e, 'self_link' => 'http://mailman.test/3.1/members/'.md5($e)], $roster)]);
    }]);
}

test('the mailman list status is cached until it is refreshed', function (): void {
    [$uid, , $email] = cachedStatusSetup();
    $roster = [];
    fakeCachedRoster($roster);

    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'pending');

    $roster = [$email];
    $component = Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'pending');

    $component->call('refresh')->assertViewHas('state', 'synced');
});

test('saving a mapping invalidates its cached status', function (): void {
    [$uid, $mapping, $email] = cachedStatusSetup();
    $roster = [];
    fakeCachedRoster($roster);
    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'pending');

    $roster = [$email];
    $mapping->update(['enabled' => false]);
    $mapping->update(['enabled' => true]);

    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'synced');
});

test('fetch errors are not cached', function (): void {
    [$uid, , $email] = cachedStatusSetup();
    $roster = null;
    fakeCachedRoster($roster);
    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'pending');

    $roster = [$email];
    Livewire::withoutLazyLoading()->test(MailmanSyncStatus::class, ['uid' => $uid])->assertViewHas('state', 'synced');
});

test('the ldap status is cached until it is refreshed', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $stale = TestLdap::makeUser();
    $role->members()->attach($stale);

    $component = Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $community->getShortCode()])
        ->assertViewHas('state', 'pending');

    $role->members()->detach($stale);
    Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $community->getShortCode()])
        ->assertViewHas('state', 'pending');

    $component->call('refresh')->assertViewHas('state', 'synced');
});

test('the ldap sync and membership changes invalidate the cached ldap status', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $status = fn () => Livewire::withoutLazyLoading()->test(LdapSyncStatus::class, ['uid' => $uid]);

    $status()->assertViewHas('state', 'synced');

    $membership = RoleMembership::create(['realm' => $uid, 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => TestLdap::member($community)->username, 'from' => today()->subMonth()]);
    $status()->assertViewHas('state', 'pending');

    Artisan::call('ldap:sync-roles', ['community' => $uid]);
    $status()->assertViewHas('state', 'synced');

    $membership->delete();
    $status()->assertViewHas('state', 'pending');
});
