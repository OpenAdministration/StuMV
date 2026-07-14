<?php

use App\Livewire\Committee\ListRoles;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the role card shows a badge with the active member count', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'staffed');
    $member1 = TestLdap::member($community);
    $member2 = TestLdap::member($community);

    RoleMembership::create([
        'role_cn' => 'staffed',
        'committee_dn' => $committee->getDn(),
        'username' => $member1->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'role_cn' => 'staffed',
        'committee_dn' => $committee->getDn(),
        'username' => $member2->username,
        'from' => today()->subMonth(),
    ]);
    actingAsModerator($community);

    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->assertSeeHtml('data-flux-badge')
        ->assertSee('2');
});
