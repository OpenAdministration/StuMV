<?php

use App\Livewire\Committee\ListRoles;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('with the active-only filter off, every role is listed', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'alpha');
    TestLdap::makeRole($committee, 'beta');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->assertSee('Role alpha')
        ->assertSee('Role beta');
});

test('the role list filters by the search term', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'alpha');
    TestLdap::makeRole($committee, 'beta');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->set('search', 'alpha')
        ->assertSee('Role alpha')
        ->assertDontSee('Role beta');
});

test('the active-only filter hides roles without members and shows those with members', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'staffed');
    TestLdap::makeRole($committee, 'empty');
    $member = TestLdap::member($community);
    RoleMembership::create([
        'role_cn' => 'staffed',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', true)
        ->assertSee('Role staffed')
        ->assertDontSee('Role empty');
});

test('sorting by a column toggles the direction', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'cn')          // same field -> toggles
        ->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'asc');
});
