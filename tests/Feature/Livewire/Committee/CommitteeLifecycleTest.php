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
