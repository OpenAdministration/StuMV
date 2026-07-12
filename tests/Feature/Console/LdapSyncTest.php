<?php

use App\Ldap\Group;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestLdap;

/**
 * The ldap:sync-* commands reconcile LDAP group/role uniqueMember lists with the
 * active memberships recorded in the database (the DB is the source of truth;
 * LDAP is the projection consumed by other services). These drive that
 * reconciliation against real fixtures: a stale LDAP member that should be
 * removed and an active DB membership that should be projected in.
 */
uses(RefreshDatabase::class);

test('ldap:sync-roles projects active DB memberships onto the LDAP role', function (): void {
    $community = newCommunity();
    $committeeName = 'sync'.bin2hex(random_bytes(3));
    $committee = TestLdap::makeCommittee($community, $committeeName);
    $role = TestLdap::makeRole($committee, 'mitglied');
    $active = TestLdap::member($community);
    $stale = TestLdap::makeUser();

    // Stale LDAP member not backed by any active DB membership.
    $role->members()->attach($stale);
    // Active DB membership that should end up in the LDAP role.
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);

    $this->artisan('ldap:sync-roles', ['committee' => $committeeName])->assertExitCode(0);

    $members = $committee->roles()->where('cn', 'mitglied')->first()->members()->get()
        ->map(fn ($m) => $m->getFirstAttribute('uid'));

    expect($members)->toContain($active->username)
        ->not->toContain($stale->getFirstAttribute('uid'));
});

test('ldap:sync-roles leaves already-correct members untouched instead of clearing and re-adding everyone', function (): void {
    $community = newCommunity();
    $committeeName = 'sync'.bin2hex(random_bytes(3));
    $committee = TestLdap::makeCommittee($community, $committeeName);
    $role = TestLdap::makeRole($committee, 'mitglied');
    $memberB = TestLdap::member($community);
    $memberA = TestLdap::member($community);
    $stale = TestLdap::makeUser();

    // Attach in a specific order (B, then A) directly, bypassing the sync.
    // A wipe-then-readd sync would rebuild uniqueMember from the DB query
    // order below (A, then B) instead, scrambling this original order.
    $role->members()->attach($memberB->ldap());
    $role->members()->attach($memberA->ldap());
    $role->members()->attach($stale);

    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $memberA->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $memberB->username,
        'from' => today()->subMonth(),
    ]);

    $this->artisan('ldap:sync-roles', ['committee' => $committeeName])->assertExitCode(0);

    $uniqueMember = \App\Ldap\Role::find($role->getDn())->getAttribute('uniqueMember');
    $posB = array_search($memberB->ldap()->getDn(), $uniqueMember);
    $posA = array_search($memberA->ldap()->getDn(), $uniqueMember);

    expect($posB)->not->toBeFalse()
        ->and($posA)->not->toBeFalse()
        ->and($posB)->toBeLessThan($posA);

    $members = \App\Ldap\Role::find($role->getDn())->members()->get()
        ->map(fn ($m) => $m->getFirstAttribute('uid'));
    expect($members)->not->toContain($stale->getFirstAttribute('uid'));
});

test('ldap:sync-roles fetches active memberships once, not once per role', function (): void {
    $community = newCommunity();
    $committee1 = TestLdap::makeCommittee($community, 'com1'.bin2hex(random_bytes(3)));
    $committee2 = TestLdap::makeCommittee($community, 'com2'.bin2hex(random_bytes(3)));
    TestLdap::makeRole($committee1, 'mitglied');
    TestLdap::makeRole($committee2, 'mitglied');
    $memberA = TestLdap::member($community);
    $memberB = TestLdap::member($community);

    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee1->getDn(),
        'username' => $memberA->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee2->getDn(),
        'username' => $memberB->username,
        'from' => today()->subMonth(),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->artisan('ldap:sync-roles')->assertExitCode(0);

    $membershipQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'role_user_relation'));

    expect($membershipQueries)->toHaveCount(1);
});

test('ldap:sync-groups fetches active memberships and group mappings once, not once per group', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr'.bin2hex(random_bytes(3)));
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group1 = TestLdap::makeGroup($community, 'grp1'.bin2hex(random_bytes(3)));
    $group2 = TestLdap::makeGroup($community, 'grp2'.bin2hex(random_bytes(3)));
    $member = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group1->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $group2->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->artisan('ldap:sync-groups')->assertExitCode(0);

    $membershipQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'role_user_relation'));
    $groupMappingQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'role_group_relation'));

    expect($membershipQueries)->toHaveCount(1)
        ->and($groupMappingQueries)->toHaveCount(1);
});

test('ldap:sync-groups projects role memberships onto the LDAP group', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));
    $active = TestLdap::member($community);
    $stale = TestLdap::makeUser();

    // The role is mapped to the group, and has one active member.
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    // Stale member currently in the group, backed by nothing.
    $group->users()->attach($stale);

    $this->artisan('ldap:sync-groups')->assertExitCode(0);

    $members = Group::find($group->getDn())->members()->get()
        ->map(fn ($m) => $m->getFirstAttribute('uid'));

    expect($members)->toContain($active->username)
        ->not->toContain($stale->getFirstAttribute('uid'));
});

test('app:move-group-roles-from-ldap-to-database imports LDAP group roles into the DB', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));

    // A role is a member of the group in LDAP; the command should record it.
    $group->members()->attach($role);

    $this->artisan('app:move-group-roles-from-ldap-to-database')->assertExitCode(0);

    $this->assertDatabaseHas('role_group_relation', [
        'group_dn' => $group->getDn(),
        'role_dn' => $role->getDn(),
    ]);
});
