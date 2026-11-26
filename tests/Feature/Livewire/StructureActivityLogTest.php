<?php

use App\Ldap\Committee;
use App\Ldap\Group;
use App\Livewire\Committee\EditCommittee;
use App\Livewire\Committee\EditRole;
use App\Livewire\Committee\ListCommitteesTree;
use App\Livewire\Committee\ListRoles;
use App\Livewire\Committee\NewCommittee;
use App\Livewire\Committee\NewRole;
use App\Livewire\Group\AddRoleToGroup;
use App\Livewire\Group\EditGroup;
use App\Livewire\Group\ListGroups;
use App\Livewire\Group\ListRolesInGroup;
use App\Livewire\Group\NewGroup;
use App\Models\ActivityLog;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('creating a committee logs it and each of its default roles by entryUUID', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(NewCommittee::class, ['realm' => $community])
        ->set('ou', 'fsr')
        ->set('description', 'Fachschaftsrat')
        ->set('roles', ['member'])
        ->call('save')
        ->assertHasNoErrors();

    $committee = Committee::findByName($community->getShortCode(), 'fsr');
    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['committee.created', 'role.created'])
        ->and($logs[0]->subject_id)->toBe($committee->getConvertedGuid())
        ->and($logs[0]->subject_label)->toBe('Fachschaftsrat')
        ->and($logs[0]->details)->toBe(['dn' => $committee->getDn(), 'description' => 'Fachschaftsrat'])
        ->and($logs[1]->details['dn'])->toBe('cn=mitglied,'.$committee->getDn());
});

test('renaming a committee logs the old and new description, saving it unchanged logs nothing', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $before = $committee->getFirstAttribute('description');
    actingAsModerator($community);

    Livewire::test(EditCommittee::class, ['realm' => $community, 'ou' => 'fsr'])->call('save');
    Livewire::test(EditCommittee::class, ['realm' => $community, 'ou' => 'fsr'])
        ->set('description', 'Fachschaftsrat')
        ->call('save');

    $log = ActivityLog::sole();
    expect($log->action)->toBe('committee.updated')
        ->and($log->details)->toBe(['description' => ['old' => $before, 'new' => 'Fachschaftsrat']]);
});

test('roles are logged when created, renamed and deleted, a deleted role with its groups', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));
    actingAsModerator($community);

    Livewire::test(NewRole::class, ['realm' => $community, 'ou' => 'fsr'])
        ->set('cn', 'kasse')
        ->set('description', 'Kasse')
        ->call('save');
    Livewire::test(EditRole::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'kasse'])
        ->set('description', 'Kassenwart')
        ->call('save');
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => 'cn=kasse,'.$committee->getDn()]);
    Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('deletePrepare', 'kasse')
        ->set('deleteConfirmText', 'kasse')
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['role.created', 'role.updated', 'role.deleted'])
        ->and($logs->pluck('subject_id')->unique())->toHaveCount(1)
        ->and($logs[1]->details)->toBe(['description' => ['old' => 'Kasse', 'new' => 'Kassenwart']])
        ->and($logs[2]->details)->toBe([
            'dn' => 'cn=kasse,'.$committee->getDn(),
            'description' => 'Kassenwart',
            'groups' => [$group->getDn()],
        ]);
});

test('deleting a committee logs it, its descendants and all their roles', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $child = TestLdap::makeCommittee($community, 'ag', parentDn: $committee->getDn());
    TestLdap::makeRole($committee, 'mitglied');
    TestLdap::makeRole($child, 'leitung');
    actingAsModerator($community);

    Livewire::test(ListCommitteesTree::class, ['realm' => $community])
        ->call('confirmDeleteCommittee', $committee->getDn())
        ->set('deleteConfirmText', 'fsr')
        ->call('deleteCommittee');

    expect(ActivityLog::where('action', 'committee.deleted')->get()->map(fn (ActivityLog $log) => $log->details['dn'])->all())
        ->toEqualCanonicalizing([$committee->getDn(), $child->getDn()])
        ->and(ActivityLog::where('action', 'role.deleted')->get()->map(fn (ActivityLog $log) => $log->details['dn'])->all())
        ->toEqualCanonicalizing(['cn=mitglied,'.$committee->getDn(), 'cn=leitung,'.$child->getDn()])
        ->and(ActivityLog::whereNull('subject_id')->exists())->toBeFalse();
});

test('groups are logged when created, renamed and deleted, a deleted group with its roles', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsAdmin($community);

    Livewire::test(NewGroup::class, ['realm' => $community])
        ->set('cn', 'newsletter')
        ->set('name', 'Newsletter')
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(EditGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->set('name', 'Rundbrief')
        ->call('save');
    GroupMembership::create(['group_dn' => Group::dnFrom($uid, 'newsletter'), 'role_dn' => $role->getDn()]);
    Livewire::test(ListGroups::class, ['realm' => $community])
        ->call('deletePrepare', $uid, 'newsletter')
        ->set('deleteConfirmText', 'newsletter')
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['group.created', 'group.updated', 'group.deleted'])
        ->and($logs->pluck('subject_id')->unique())->toHaveCount(1)
        ->and($logs[1]->details)->toBe(['description' => ['old' => 'Newsletter', 'new' => 'Rundbrief']])
        ->and($logs[2]->details)->toBe([
            'dn' => Group::dnFrom($uid, 'newsletter'),
            'description' => 'Rundbrief',
            'roles' => [$role->getDn()],
        ]);
});

test('linking roles to a group and unlinking them is logged on the group', function (): void {
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
        ->assertHasNoErrors();
    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'grp'])
        ->call('deletePrepare', GroupMembership::where('role_dn', $leitung->getDn())->value('id'))
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['group_role.added', 'group_role.added', 'group_role.removed'])
        ->and($logs->pluck('subject_id')->unique()->all())->toBe([$group->fresh()->getConvertedGuid()])
        ->and($logs->map(fn (ActivityLog $log) => $log->details['role'])->all())->toBe([$mitglied->getDn(), $leitung->getDn(), $leitung->getDn()]);
});
