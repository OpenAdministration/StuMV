<?php

use App\Ldap\Committee;
use App\Livewire\Committee\ListCommitteesTree;
use App\Livewire\Committee\ListRoles;
use App\Livewire\Committee\TerminateRoleMemberships;
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

    Livewire::test(ListCommitteesTree::class, ['uid' => $community])
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

    Livewire::test(ListCommitteesTree::class, ['uid' => $community])
        ->call('confirmDeleteCommittee', $committee->getDn())
        ->assertForbidden();

    expect(Committee::findByName($uid, 'fsr'))->not->toBeNull();
});

test('a moderator can delete a role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => 'fsr'])
        ->call('deletePrepare', 'mitglied')
        ->call('deleteCommit');

    expect($committee->roles()->where('cn', 'mitglied')->exists())->toBeFalse();
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

    Livewire::test(TerminateRoleMemberships::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('membershipsToTerminate', [$membership->id])
        ->set('terminationDate', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    expect($membership->fresh()->until->format('Y-m-d'))->toBe(today()->format('Y-m-d'));
});
