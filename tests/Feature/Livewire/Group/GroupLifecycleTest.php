<?php

use App\Ldap\Group;
use App\Livewire\Group\AddRoleToGroup;
use App\Livewire\Group\EditGroup;
use App\Livewire\Group\NewGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('an admin can map a role to a group', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('save');

    $this->assertDatabaseHas('role_group_relation', [
        'group_dn' => $group->getDn(),
        'role_dn' => $role->getDn(),
    ]);
});

test('an admin can create a group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(NewGroup::class, ['uid' => $community])
        ->set('cn', 'newsletter')
        ->set('name', 'Newsletter Editors')
        ->call('save')
        ->assertHasNoErrors();

    $group = Group::find(Group::dnFrom($uid, 'newsletter'));
    expect($group)->not->toBeNull()
        ->and($group->getFirstAttribute('description'))->toBe('Newsletter Editors');
});

test('an admin can rename a group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(EditGroup::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->set('name', 'Newsletter Team')
        ->call('save')
        ->assertHasNoErrors();

    expect(Group::find(Group::dnFrom($uid, 'newsletter'))->getFirstAttribute('description'))
        ->toBe('Newsletter Team');
});
