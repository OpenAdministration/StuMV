<?php

use App\Livewire\Tools\UnusedRoles;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * The UnusedRoles tool lists roles that have no database memberships (and
 * committees whose roles are all unused and which have no children), to help
 * moderators prune stale structure.
 */
uses(RefreshDatabase::class);

test('it lists roles without memberships and hides roles that are in use', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'used');
    TestLdap::makeRole($committee, 'unused');
    $member = TestLdap::member($community);
    actingAsModerator($community);

    RoleMembership::create([
        'role_cn' => 'used',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);

    Livewire::test(UnusedRoles::class, ['realm' => $community])
        ->call('loadUnusedRoles')
        ->assertStatus(200)
        ->assertSee('Role unused')
        ->assertDontSee('Role used');
});

test('loading is deferred until loadUnusedRoles runs', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'unused');
    actingAsModerator($community);

    Livewire::test(UnusedRoles::class, ['realm' => $community])
        ->assertSet('ready', false)
        ->assertDontSee('Role unused')
        ->assertSeeHtml('wire:target="loadUnusedRoles"');
});
