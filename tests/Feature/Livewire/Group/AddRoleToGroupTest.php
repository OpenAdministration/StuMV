<?php

use App\Ldap\Community;
use App\Livewire\Group\AddRoleToGroup;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('renders the add-role-to-group screen for an admin', function (): void {
    actingAsAdmin('demo');

    Livewire::test(AddRoleToGroup::class, ['uid' => Community::findByUid('demo'), 'cn' => 'some-group'])
        ->assertStatus(200)
        ->assertSet('group_cn', 'some-group');
});

test('the role select excludes roles already added to the group', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $addedRole = TestLdap::makeRole($committee, 'mitglied');
    $eligibleRole = TestLdap::makeRole($committee, 'leitung');
    $group = TestLdap::makeGroup($community, 'grp');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $addedRole->getDn()]);
    actingAsAdmin($community);

    $roleDns = Livewire::test(AddRoleToGroup::class, ['uid' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $committee->getDn())
        ->viewData('roles')
        ->map(fn ($role) => $role->getDn());

    expect($roleDns)->toContain($eligibleRole->getDn())
        ->not->toContain($addedRole->getDn());
});
