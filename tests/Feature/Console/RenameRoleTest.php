<?php

use App\Ldap\Role;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('app:rename-role relocates the LDAP entry, DB memberships and group relations', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'kassenwart');
    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));
    $member = TestLdap::member($community);

    $oldDn = $role->getDn();

    RoleMembership::create([
        'role_cn' => 'kassenwart',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $oldDn]);

    $this->artisan('app:rename-role', [
        'community' => $uid,
        'committee' => 'fsr',
        'role' => 'kassenwart',
        'new-role' => 'schatzmeister',
    ])->assertExitCode(0);

    $renamed = Role::findOrFail('cn=schatzmeister,'.$committee->getDn());
    expect(Role::find($oldDn))->toBeNull();

    $this->assertDatabaseHas('role_user_relation', [
        'role_cn' => 'schatzmeister',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
    ]);
    $this->assertDatabaseMissing('role_user_relation', [
        'role_cn' => 'kassenwart',
    ]);

    $this->assertDatabaseHas('role_group_relation', [
        'group_dn' => $group->getDn(),
        'role_dn' => $renamed->getDn(),
    ]);
    $this->assertDatabaseMissing('role_group_relation', [
        'role_dn' => $oldDn,
    ]);
});

test('app:rename-role fails for an unknown role', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'fsr');

    $this->artisan('app:rename-role', [
        'community' => $uid,
        'committee' => 'fsr',
        'role' => 'does-not-exist',
        'new-role' => 'schatzmeister',
    ])->assertExitCode(1);
});

test('app:rename-role fails when the old and new name are the same', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'kassenwart');

    $this->artisan('app:rename-role', [
        'community' => $uid,
        'committee' => 'fsr',
        'role' => 'kassenwart',
        'new-role' => 'kassenwart',
    ])->assertExitCode(1);
});

test('app:rename-role fails when a role with the new name already exists', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'kassenwart');
    TestLdap::makeRole($committee, 'schatzmeister');

    $this->artisan('app:rename-role', [
        'community' => $uid,
        'committee' => 'fsr',
        'role' => 'kassenwart',
        'new-role' => 'schatzmeister',
    ])->assertExitCode(1);
});
