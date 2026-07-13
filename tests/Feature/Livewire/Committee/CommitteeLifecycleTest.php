<?php

use App\Ldap\Committee;
use App\Livewire\Committee\EditCommittee;
use App\Livewire\Committee\NewCommittee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a moderator can create a committee with its default roles', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsModerator($community);

    Livewire::test(NewCommittee::class, ['uid' => $community])
        ->set('ou', 'fsr')
        ->set('description', 'Fachschaftsrat')
        ->set('roles', ['member', 'head'])
        ->call('save')
        ->assertHasNoErrors();

    $committee = Committee::findByName($uid, 'fsr');
    expect($committee)->not->toBeNull()
        ->and($committee->getFirstAttribute('description'))->toBe('Fachschaftsrat')
        ->and($committee->roles()->where('cn', 'mitglied')->exists())->toBeTrue()
        ->and($committee->roles()->where('cn', 'leitung')->exists())->toBeTrue();
});

test('committee short names are validated', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(NewCommittee::class, ['uid' => $community])
        ->set('ou', 'Not Valid!')
        ->set('description', 'whatever')
        ->call('save')
        ->assertHasErrors('ou');
});

test('a moderator can rename a committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    Livewire::test(EditCommittee::class, ['uid' => $community, 'ou' => 'fsr'])
        ->set('description', 'Renamed Committee')
        ->call('save')
        ->assertHasNoErrors();

    expect(Committee::findByName($uid, 'fsr')->getFirstAttribute('description'))
        ->toBe('Renamed Committee');
});

test('a committee moderator can create a sub-committee under their own committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $moderator = TestLdap::committeeModerator($parent, $community);
    $this->actingAs($moderator);

    Livewire::test(NewCommittee::class, ['uid' => $community])
        ->set('parent_dn', $parent->getDn())
        ->set('ou', 'child')
        ->set('description', 'Child Committee')
        ->call('save')
        ->assertHasNoErrors();

    expect(Committee::findByName($uid, 'child'))->not->toBeNull();
});

test('a committee moderator cannot create a sub-committee under an unrelated committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $ownCommittee = TestLdap::makeCommittee($community, 'own');
    $unrelatedCommittee = TestLdap::makeCommittee($community, 'unrelated');
    $moderator = TestLdap::committeeModerator($ownCommittee, $community);
    $this->actingAs($moderator);

    Livewire::test(NewCommittee::class, ['uid' => $community])
        ->set('parent_dn', $unrelatedCommittee->getDn())
        ->set('ou', 'sneaky-child')
        ->set('description', 'Sneaky Child')
        ->call('save')
        ->assertForbidden();

    expect(Committee::findByName($uid, 'sneaky-child'))->toBeNull();
});

test('a committee moderator cannot create a top-level committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $ownCommittee = TestLdap::makeCommittee($community, 'own');
    $moderator = TestLdap::committeeModerator($ownCommittee, $community);
    $this->actingAs($moderator);

    Livewire::test(NewCommittee::class, ['uid' => $community])
        ->set('parent_dn', '')
        ->set('ou', 'new-top-level')
        ->set('description', 'New Top Level')
        ->call('save')
        ->assertForbidden();

    expect(Committee::findByName($uid, 'new-top-level'))->toBeNull();
});

test('a committee moderator only sees their own committee subtree as selectable parents', function (): void {
    $community = newCommunity();
    $ownCommittee = TestLdap::makeCommittee($community, 'own');
    $ownChild = TestLdap::makeCommittee($community, 'own-child', parentDn: $ownCommittee->getDn());
    $unrelatedCommittee = TestLdap::makeCommittee($community, 'unrelated');
    $moderator = TestLdap::committeeModerator($ownCommittee, $community);
    $this->actingAs($moderator);

    $dns = array_keys(
        Livewire::test(NewCommittee::class, ['uid' => $community])->viewData('select_parents')
    );

    expect($dns)->toContain($ownCommittee->getDn())
        ->toContain($ownChild->getDn())
        ->not->toContain($unrelatedCommittee->getDn());
});

test('a community moderator sees every committee as a selectable parent', function (): void {
    $community = newCommunity();
    $committeeA = TestLdap::makeCommittee($community, 'committee-a');
    $committeeB = TestLdap::makeCommittee($community, 'committee-b');
    actingAsModerator($community);

    $dns = array_keys(
        Livewire::test(NewCommittee::class, ['uid' => $community])->viewData('select_parents')
    );

    expect($dns)->toContain($committeeA->getDn())
        ->toContain($committeeB->getDn());
});
