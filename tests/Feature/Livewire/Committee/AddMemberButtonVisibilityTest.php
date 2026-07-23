<?php

use App\Livewire\Committee\ListRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a moderator sees the add member button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->assertSeeHtml(__('common.add_member'));
});

test('a super admin sees the add member button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsSuperAdmin();

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->assertSeeHtml(__('common.add_member'));
});

test('a plain member does not see the add member button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsMember($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->assertDontSeeHtml(__('common.add_member'));
});
