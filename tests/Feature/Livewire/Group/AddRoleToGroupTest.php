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

    Livewire::test(AddRoleToGroup::class, ['realm' => Community::findByUid('demo'), 'cn' => 'some-group'])
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

    $roleDns = Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $committee->getDn())
        ->viewData('roles')
        ->map(fn ($role) => $role->getDn());

    expect($roleDns)->toContain($eligibleRole->getDn())
        ->not->toContain($addedRole->getDn());
});

test('several roles of a committee can be added at once', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $mitglied = TestLdap::makeRole($committee, 'mitglied');
    $leitung = TestLdap::makeRole($committee, 'leitung');
    $group = TestLdap::makeGroup($community, 'grp');
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dns', [$mitglied->getDn(), $leitung->getDn()])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.groups.roles', ['realm' => $community->getShortCode(), 'cn' => 'grp']));

    expect(GroupMembership::where('group_dn', $group->getDn())->pluck('role_dn')->sort()->values()->all())
        ->toBe(collect([$mitglied->getDn(), $leitung->getDn()])->sort()->values()->all());
});

test('changing the committee clears the selected roles', function (): void {
    $community = newCommunity();
    $fsr = TestLdap::makeCommittee($community, 'fsr');
    $stura = TestLdap::makeCommittee($community, 'stura');
    $role = TestLdap::makeRole($fsr, 'mitglied');
    TestLdap::makeGroup($community, 'grp');
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $fsr->getDn())
        ->set('selected_role_dns', [$role->getDn()])
        ->set('selected_committee_dn', $stura->getDn())
        ->assertSet('selected_role_dns', [])
        ->assertSet('roleFieldKey', 2);
});

test('at least one role is required', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeGroup($community, 'grp');
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $committee->getDn())
        ->call('save')
        ->assertHasErrors(['selected_role_dns' => 'required']);
});

test('a role that is already added is rejected', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $mitglied = TestLdap::makeRole($committee, 'mitglied');
    $leitung = TestLdap::makeRole($committee, 'leitung');
    $group = TestLdap::makeGroup($community, 'grp');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $mitglied->getDn()]);
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dns', [$mitglied->getDn(), $leitung->getDn()])
        ->call('save')
        ->assertHasErrors(['selected_role_dns']);

    expect(GroupMembership::where('group_dn', $group->getDn())->count())->toBe(1);
});

test('roles outside the selected committee are refused', function (): void {
    $community = newCommunity();
    $fsr = TestLdap::makeCommittee($community, 'fsr');
    $stura = TestLdap::makeCommittee($community, 'stura');
    $foreignRole = TestLdap::makeRole($stura, 'mitglied');
    $group = TestLdap::makeGroup($community, 'grp');
    actingAsAdmin($community);

    Livewire::test(AddRoleToGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->set('selected_committee_dn', $fsr->getDn())
        ->set('selected_role_dns', [$foreignRole->getDn()])
        ->call('save')
        ->assertStatus(404);

    expect(GroupMembership::where('group_dn', $group->getDn())->count())->toBe(0);
});
