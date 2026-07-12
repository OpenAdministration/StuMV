<?php

use App\Livewire\Group\ListRolesInGroup;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('deleting a role from a group removes only that specific row, even with duplicates', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    // Two duplicate relationship rows for the exact same group/role pair.
    $first = GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $second = GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);

    Livewire::test(ListRolesInGroup::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->call('deletePrepare', $first->id)
        ->call('deleteCommit');

    expect(GroupMembership::find($first->id))->toBeNull()
        ->and(GroupMembership::find($second->id))->not->toBeNull();
});

test('the roles-in-group list shows one row per membership row', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);

    $html = Livewire::test(ListRolesInGroup::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->html();

    expect(substr_count($html, 'Role mitglied'))->toBe(2);
});
