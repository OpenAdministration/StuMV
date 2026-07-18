<?php

use App\Ldap\User;
use App\Livewire\Committee\ListCommitteeModerators;
use App\Livewire\Committee\NewCommitteeModerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * Who moderates a committee is visible to any community member (matching
 * Realm\ListModerators at the community level) - only adding/removing
 * moderators is restricted to those who already moderate the committee (or
 * an ancestor of it), see ListCommitteeModerators::deletePrepare()/
 * deleteCommit() and NewCommitteeModerator.
 */
uses(RefreshDatabase::class);

test('a committee moderator can add another moderator to their committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    $newMod = TestLdap::member($community);
    $this->actingAs($moderator);

    Livewire::test(NewCommitteeModerator::class, ['realm' => $community, 'ou' => 'fsr'])
        ->set('dn', [$newMod->ldap()->getDn()])
        ->call('save');

    expect($committee->moderatorsGroup()->members()->contains($newMod->ldap()))->toBeTrue();
});

test('a plain member can view a committee\'s moderators but cannot add one', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    $member = TestLdap::member($community);
    $this->actingAs($member);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->assertOk()
        ->assertSee($moderator->full_name);

    Livewire::test(NewCommitteeModerator::class, ['realm' => $community, 'ou' => 'fsr'])
        ->assertForbidden();
});

test('a plain member cannot remove a committee moderator', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $target = TestLdap::committeeModerator($committee, $community);
    $member = TestLdap::member($community);
    $this->actingAs($member);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->assertForbidden();
});

test('a committee moderator of a different committee can view but not manage this one\'s moderators', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    $moderatorA = TestLdap::committeeModerator($committeeA, $community);
    $targetB = TestLdap::committeeModerator($committeeB, $community);
    $this->actingAs($moderatorA);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'committee-b'])
        ->call('loadModerators')
        ->assertOk()
        ->assertSee($targetB->full_name)
        ->call('deletePrepare', $targetB->username)
        ->assertForbidden();
});

test('deletePrepare shows the confirmation modal', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $actor = TestLdap::committeeModerator($committee, $community);
    $target = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($actor);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->assertDispatched('modal-show', name: 'delete')
        ->assertSet('deleteModeratorUsername', $target->username);
});

test('deleteCommit removes the moderator and closes the modal', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $actor = TestLdap::committeeModerator($committee, $community);
    $target = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($actor);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $target->username)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete');

    $ldapUser = User::findByUsername($target->username);
    expect($committee->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});

test('a moderator can remove themselves without the page crashing on re-render', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    $this->actingAs($moderator);

    Livewire::test(ListCommitteeModerators::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadModerators')
        ->call('deletePrepare', $moderator->username)
        ->call('deleteCommit')
        ->assertOk()
        ->assertDispatched('modal-close', name: 'delete');

    $ldapUser = User::findByUsername($moderator->username);
    expect($committee->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});
