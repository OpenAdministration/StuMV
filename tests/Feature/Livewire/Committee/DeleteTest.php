<?php

use App\Ldap\Committee;
use App\Livewire\Committee\ListCommitteesTree;
use App\Livewire\Committee\ListRoles;
use App\Livewire\Committee\TerminateRoleMemberships;
use App\Models\ActivityLog;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a moderator can delete a committee once the name is confirmed', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('confirmDeleteCommittee', $committee->getDn())
        ->set('deleteConfirmText', 'fsr')
        ->call('deleteCommittee');

    expect(Committee::findByName($uid, 'fsr'))->toBeNull();
});

test('a plain member cannot delete a committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsMember($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('confirmDeleteCommittee', $committee->getDn())
        ->assertForbidden();

    expect(Committee::findByName($uid, 'fsr'))->not->toBeNull();
});

test('a moderator can delete a role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('deletePrepare', 'mitglied')
        ->set('deleteConfirmText', 'mitglied')
        ->call('deleteCommit');

    expect($committee->roles()->where('cn', 'mitglied')->exists())->toBeFalse();
});

test('deleting a role logs every person removed from it', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $members = [TestLdap::member($community), TestLdap::member($community)];
    foreach ($members as $member) {
        RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-01-01']);
    }
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('deletePrepare', 'mitglied')
        ->set('deleteConfirmText', 'mitglied')
        ->call('deleteCommit');

    $logs = ActivityLog::where('action', 'role_membership.deleted')->get();
    expect($logs->map(fn (ActivityLog $log): ?string => $log->details['person'])->all())
        ->toEqualCanonicalizing(array_map(fn ($member) => $member->uid, $members));
});

test('deleting a committee logs every person removed from its and its descendants\' roles', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $child = TestLdap::makeCommittee($community, 'ag', parentDn: $committee->getDn());
    TestLdap::makeRole($committee, 'mitglied');
    TestLdap::makeRole($child, 'leitung');
    $member = TestLdap::member($community);
    RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-01-01']);
    RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'leitung', 'committee_dn' => $child->getDn(), 'username' => $member->username, 'from' => '2026-01-01']);
    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('confirmDeleteCommittee', $committee->getDn())
        ->set('deleteConfirmText', 'fsr')
        ->call('deleteCommittee');

    expect(ActivityLog::where('action', 'role_membership.deleted')->pluck('subject_label')->all())
        ->toEqualCanonicalizing(['mitglied (fsr)', 'leitung (ag)']);
});

test('a moderator can terminate an active role membership', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    actingAsModerator($community);

    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);

    Livewire::test(TerminateRoleMemberships::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('membershipsToTerminate', [$membership->id])
        ->set('terminationDate', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    expect($membership->fresh()->until->format('Y-m-d'))->toBe(today()->format('Y-m-d'));
});
