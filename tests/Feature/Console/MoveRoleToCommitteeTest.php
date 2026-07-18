<?php

use App\Ldap\Role;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('app:move-role-to-committee relocates the LDAP entry, DB memberships and group relations', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $source = TestLdap::makeCommittee($community, 'source');
    $target = TestLdap::makeCommittee($community, 'target');
    $role = TestLdap::makeRole($source, 'mitglied');
    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));
    $member = TestLdap::member($community);

    $oldDn = $role->getDn();

    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $source->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $oldDn]);

    $this->artisan('app:move-role-to-committee', [
        'community' => $uid,
        'committee' => 'source',
        'role' => 'mitglied',
        'target-committee' => 'target',
    ])->assertExitCode(0);

    $moved = Role::findOrFail('cn=mitglied,'.$target->getDn());
    expect($moved->getParentDn())->toBe($target->getDn());

    $this->assertDatabaseHas('role_user_relation', [
        'role_cn' => 'mitglied',
        'committee_dn' => $target->getDn(),
        'username' => $member->username,
    ]);
    $this->assertDatabaseMissing('role_user_relation', [
        'committee_dn' => $source->getDn(),
    ]);

    $this->assertDatabaseHas('role_group_relation', [
        'group_dn' => $group->getDn(),
        'role_dn' => $moved->getDn(),
    ]);
    $this->assertDatabaseMissing('role_group_relation', [
        'role_dn' => $oldDn,
    ]);
});

test('app:move-role-to-committee fails for an unknown role', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'source');
    TestLdap::makeCommittee($community, 'target');

    $this->artisan('app:move-role-to-committee', [
        'community' => $uid,
        'committee' => 'source',
        'role' => 'does-not-exist',
        'target-committee' => 'target',
    ])->assertExitCode(1);
});

test('app:move-role-to-committee fails when source and target committee are the same', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'source');
    TestLdap::makeRole($committee, 'mitglied');

    $this->artisan('app:move-role-to-committee', [
        'community' => $uid,
        'committee' => 'source',
        'role' => 'mitglied',
        'target-committee' => 'source',
    ])->assertExitCode(1);
});
