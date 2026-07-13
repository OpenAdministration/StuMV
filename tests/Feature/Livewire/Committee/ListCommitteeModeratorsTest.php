<?php

use App\Ldap\User;
use App\Livewire\Committee\ListCommitteeModerators;
use App\Livewire\Committee\NewCommitteeModerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a committee moderator can add another moderator to their committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    $newMod = TestLdap::member($community);
    $this->actingAs($moderator);

    Livewire::test(NewCommitteeModerator::class, ['uid' => $community, 'ou' => 'fsr'])
        ->set('dn', [$newMod->ldap()->getDn()])
        ->call('save');

    expect($committee->moderatorsGroup()->members()->contains($newMod->ldap()))->toBeTrue();
});

test('a plain member cannot view or add a committee\'s moderators', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'fsr'])
        ->assertForbidden();

    Livewire::test(NewCommitteeModerator::class, ['uid' => $community, 'ou' => 'fsr'])
        ->assertForbidden();
});

test('a committee moderator of a different committee cannot manage this one\'s moderators', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    $moderatorA = TestLdap::committeeModerator($committeeA, $community);
    $this->actingAs($moderatorA);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'committee-b'])
        ->assertForbidden();
});

test('deletePrepare shows the confirmation modal', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $actor = TestLdap::committeeModerator($committee, $community);
    $target = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($actor);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->assertDispatched('modal-show', name: 'delete')
        ->assertSet('deleteModeratorUsername', $target->username);
});

test('deleteCommit removes the moderator and closes the modal', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    // Two distinct moderators - the acting user removes the other one, not
    // themselves, so their own authorization to view this page survives the
    // action (self-removal is covered separately below).
    $actor = TestLdap::committeeModerator($committee, $community);
    $target = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($actor);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete');

    $ldapUser = User::findByUsername($target->username);
    expect($committee->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});

test('a moderator removing themselves is redirected to the roles overview instead of crashing on re-render', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($moderator);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $moderator->username)
        ->call('deleteCommit')
        ->assertRedirect(route('committees.roles', ['uid' => $community->getShortCode(), 'ou' => 'fsr']));

    $ldapUser = User::findByUsername($moderator->username);
    expect($committee->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});

test('a community moderator removing a committee moderator is not redirected, since they still moderate the community', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $target = TestLdap::committeeModerator($committee, $community);
    actingAsModerator($community);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete')
        ->assertNoRedirect();
});
