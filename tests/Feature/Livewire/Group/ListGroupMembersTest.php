<?php

use App\Livewire\Group\ListGroupMembers;
use App\Livewire\Group\ListGroups;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a member with an active role membership already synced to LDAP shows as synced', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $active = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    $group->users()->attach($active->ldap());

    actingAsAdmin($community);

    Livewire::test(ListGroupMembers::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->assertSee($active->ldap()->getFirstAttribute('cn'))
        ->assertSee(__('groups.status_synced'))
        ->assertDontSee(__('groups.status_pending'))
        ->assertDontSee(__('groups.status_stale'));
});

test('a member with an active role membership not yet synced to LDAP shows as pending', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $active = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    // Not yet attached to the group's LDAP uniqueMember - simulates having
    // not gone through a "ldap:sync-groups" run yet.

    actingAsAdmin($community);

    Livewire::test(ListGroupMembers::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->assertSee($active->ldap()->getFirstAttribute('cn'))
        ->assertSee(__('groups.status_pending'))
        ->assertDontSee(__('groups.status_synced'));
});

test('a member present in LDAP without a backing active role membership shows as stale', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $stale = TestLdap::makeUser();
    $group->users()->attach($stale);

    actingAsAdmin($community);

    Livewire::test(ListGroupMembers::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->assertSee($stale->getFirstAttribute('cn'))
        ->assertSee(__('groups.status_stale'))
        ->assertDontSee(__('groups.status_synced'))
        ->assertDontSee(__('groups.status_pending'));
});

test('the group members search filters the list', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $alpha = TestLdap::member($community);
    $beta = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    foreach ([$alpha, $beta] as $member) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => $member->username,
            'from' => today()->subMonth(),
        ]);
    }
    $alpha->ldap()->fill(['cn' => 'Alpha Alison'])->save();
    $beta->ldap()->fill(['cn' => 'Beta Baker'])->save();

    actingAsAdmin($community);

    Livewire::test(ListGroupMembers::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->set('search', 'Alpha')
        ->assertSee('Alpha Alison')
        ->assertDontSee('Beta Baker');
});

test('a group with no members shows a warning callout', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroupMembers::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->assertSeeHtml('data-flux-callout');
});

test('the groups list links to the group members page', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->assertSeeHtml(route('realms.groups.members', ['uid' => $community->getShortCode(), 'cn' => 'newsletter']));
});
