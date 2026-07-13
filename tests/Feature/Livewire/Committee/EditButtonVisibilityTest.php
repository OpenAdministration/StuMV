<?php

use App\Livewire\Committee\ListRoleMembers;
use App\Livewire\Committee\ListRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * The "edit committee" button on the roles overview and the "edit role"
 * button on the role members page both sit next to their heading. Committees
 * can only be edited by community moderators (CommitteePolicy::edit is
 * community-moderator-only), while roles can be edited by committee
 * moderators too (RolePolicy::edit is committee-moderator-aware).
 */
uses(RefreshDatabase::class);

test('a community moderator sees the edit committee button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    $editUrl = route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']);

    Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadRoles')
        ->assertSeeHtml('href="'.$editUrl.'"');
});

test('a committee moderator does not see the edit committee button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $moderator = TestLdap::committeeModerator($committee, $community);
    test()->actingAs($moderator);

    $editUrl = route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']);

    Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadRoles')
        ->assertDontSeeHtml('href="'.$editUrl.'"');
});

test('a plain member does not see the edit committee button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsMember($community);

    $editUrl = route('committees.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr']);

    Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadRoles')
        ->assertDontSeeHtml('href="'.$editUrl.'"');
});

test('a committee moderator sees the edit role button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $moderator = TestLdap::committeeModerator($committee, $community);
    test()->actingAs($moderator);

    $editUrl = route('committees.roles.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr', 'cn' => 'mitglied']);

    Livewire::test(ListRoleMembers::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->assertSeeHtml('href="'.$editUrl.'"');
});

test('a plain member does not see the edit role button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsMember($community);

    $editUrl = route('committees.roles.edit', ['uid' => $community->getShortCode(), 'ou' => 'fsr', 'cn' => 'mitglied']);

    Livewire::test(ListRoleMembers::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->assertDontSeeHtml('href="'.$editUrl.'"');
});
