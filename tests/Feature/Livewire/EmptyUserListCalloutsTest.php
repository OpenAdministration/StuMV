<?php

use App\Livewire\Committee\ListCommitteeModerators;
use App\Livewire\Committee\ListRoleMembers;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListMembers;
use App\Livewire\Realm\ListModerators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * "No X found" empty states across user lists were replaced with flux:callout
 * warning callouts (circle-alert icon) instead of a plain gray span.
 */
uses(RefreshDatabase::class);

test('the admins list shows a warning callout when there are no admins', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(ListAdmins::class, ['uid' => $community])
        ->call('loadAdmins')
        ->assertSeeHtml('data-flux-callout')
        ->assertSee(__('realms.no_admins_found'));
});

test('the moderators list shows a warning callout when there are no moderators', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->assertSeeHtml('data-flux-callout')
        ->assertSee(__('realms.no_moderators_found'));
});

test('the members list shows a warning callout when there are no members', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->assertSeeHtml('data-flux-callout');
});

test('the committee moderators list shows a warning callout when there are no moderators', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsMember($community);

    Livewire::test(ListCommitteeModerators::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadModerators')
        ->assertSeeHtml('data-flux-callout')
        ->assertSee(__('committees.no_mods_found'));
});

test('the role members list shows a warning callout when the role has no members', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    Livewire::test(ListRoleMembers::class, [
        'uid' => $community,
        'ou' => $committee->getFirstAttribute('ou'),
        'cn' => $role->getFirstAttribute('cn'),
    ])
        ->call('loadMembers')
        ->assertSeeHtml('data-flux-callout')
        ->assertSee(__('roles.no_members_found'));
});
