<?php

use App\Livewire\SyncLdap;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a success toast is shown after syncing LDAP', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap')
        ->assertDispatched('toast-show');
});

test('syncing only covers the given realm, not every realm', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Artisan::shouldReceive('call')
        ->once()
        ->with('ldap:sync-roles', ['community' => $community->getShortCode()])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('ldap:sync-groups', ['community' => $community->getShortCode()])
        ->andReturn(0);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap');
});

test('an admin of a different realm cannot use this component for a realm they don\'t administer', function (): void {
    $ownRealm = newCommunity();
    $otherRealm = newCommunity();
    actingAsAdmin($ownRealm);

    Livewire::test(SyncLdap::class, ['uid' => $otherRealm->getShortCode()])
        ->assertStatus(403);
});

test('a moderator cannot use this component', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('a member cannot use this component', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('a super admin can use this component for any realm', function (): void {
    $community = newCommunity();
    actingAsSuperAdmin();

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('syncLdap')
        ->assertDispatched('toast-show');
});

test('the sync-ldap button is shown to an admin on their own realm\'s dashboard', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertSee('syncLdap', escape: false);
});

test('the sync-ldap button is hidden from a moderator', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertDontSee('syncLdap', escape: false);
});

test('the sync-ldap button is hidden from a plain member', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]));

    $response->assertOk()->assertDontSee('syncLdap', escape: false);
});

test('the preview only shows committees/roles and groups that would actually change, with resolved names and links', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $changedCommittee = TestLdap::makeCommittee($community, 'chg'.bin2hex(random_bytes(3)));
    $changedRole = TestLdap::makeRole($changedCommittee, 'mitglied');
    $active = TestLdap::member($community);
    $stale = TestLdap::makeUser();
    $changedRole->members()->attach($stale);
    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $changedCommittee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);

    $unchangedCommittee = TestLdap::makeCommittee($community, 'unc'.bin2hex(random_bytes(3)));
    TestLdap::makeRole($unchangedCommittee, 'mitglied');

    $changedGroup = TestLdap::makeGroup($community, 'chggrp'.bin2hex(random_bytes(3)));
    GroupMembership::create(['group_dn' => $changedGroup->getDn(), 'role_dn' => $changedRole->getDn()]);
    $unchangedGroup = TestLdap::makeGroup($community, 'uncgrp'.bin2hex(random_bytes(3)));

    Livewire::test(SyncLdap::class, ['uid' => $community->getShortCode()])
        ->call('openSyncPreview')
        ->assertSee($changedCommittee->getFirstAttribute('description'))
        ->assertSee($changedRole->getFirstAttribute('description'))
        ->assertSee('Test '.$active->username)
        ->assertSee($stale->getFirstAttribute('uid'))
        ->assertSee($changedGroup->getFirstAttribute('cn'))
        ->assertDontSee($unchangedCommittee->getFirstAttribute('description'))
        ->assertDontSee($unchangedGroup->getFirstAttribute('cn'))
        ->assertSee(route('committees.roles.members', [
            'realm' => $community->getShortCode(),
            'ou' => $changedCommittee->getFirstAttribute('ou'),
            'cn' => $changedRole->getFirstAttribute('cn'),
        ]), false)
        ->assertSee(route('profile', ['realm' => $community->getShortCode(), 'username' => $active->username]), false)
        ->assertSee(route('realms.groups.members', ['realm' => $community->getShortCode(), 'cn' => $changedGroup->getFirstAttribute('cn')]), false);
});
