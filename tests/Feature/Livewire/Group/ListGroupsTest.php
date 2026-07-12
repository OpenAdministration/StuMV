<?php

use App\Ldap\Group;
use App\Livewire\Group\ListGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('an admin can delete a group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->call('deletePrepare', $uid, 'newsletter')
        ->call('deleteCommit');

    expect(Group::find(Group::dnFrom($uid, 'newsletter')))->toBeNull();
});

test('deletePrepare fills in the group name for the confirmation modal', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->call('deletePrepare', $uid, 'newsletter')
        ->assertSet('deleteGroupName', 'newsletter')
        ->assertSee('newsletter');
});

test('groups are listed without using the LDAP slice/VLV query', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'grp1');
    TestLdap::makeGroup($community, 'grp2');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->assertSee('grp1')
        ->assertSee('grp2');
});

test('the group search filters the list', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'alpha');
    TestLdap::makeGroup($community, 'beta');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->set('search', 'alpha')
        ->assertSee('alpha')
        ->assertDontSee('beta');
});
