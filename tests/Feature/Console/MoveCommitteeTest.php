<?php

use App\Ldap\Committee;
use App\Ldap\Role;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('app:move-committee relocates the committee, its nested descendant, their roles, DB memberships and group relations', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $target = TestLdap::makeCommittee($community, 'target');
    $source = TestLdap::makeCommittee($community, 'source');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $source->getDn());

    $sourceRole = TestLdap::makeRole($source, 'mitglied');
    $childRole = TestLdap::makeRole($child, 'kassenwart');

    $group = TestLdap::makeGroup($community, 'grp'.bin2hex(random_bytes(3)));
    $member = TestLdap::member($community);

    $oldSourceDn = $source->getDn();
    $oldChildDn = $child->getDn();
    $oldSourceRoleDn = $sourceRole->getDn();
    $oldChildRoleDn = $childRole->getDn();

    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $oldSourceDn,
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'role_cn' => 'kassenwart',
        'committee_dn' => $oldChildDn,
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $oldSourceRoleDn]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $oldChildRoleDn]);

    $this->artisan('app:move-committee', [
        'community' => $uid,
        'committee' => 'source',
        'target-committee' => 'target',
    ])->assertExitCode(0);

    $movedSource = Committee::findOrFail('ou=source,'.$target->getDn());
    $movedChild = Committee::findOrFail('ou=child,'.$movedSource->getDn());

    expect(Committee::find($oldSourceDn))->toBeNull()
        ->and(Committee::find($oldChildDn))->toBeNull();

    $newSourceRoleDn = 'cn=mitglied,'.$movedSource->getDn();
    $newChildRoleDn = 'cn=kassenwart,'.$movedChild->getDn();

    expect(Role::find($newSourceRoleDn))->not->toBeNull()
        ->and(Role::find($newChildRoleDn))->not->toBeNull();

    $this->assertDatabaseHas('role_user_relation', [
        'role_cn' => 'mitglied',
        'committee_dn' => $movedSource->getDn(),
    ]);
    $this->assertDatabaseHas('role_user_relation', [
        'role_cn' => 'kassenwart',
        'committee_dn' => $movedChild->getDn(),
    ]);
    $this->assertDatabaseMissing('role_user_relation', ['committee_dn' => $oldSourceDn]);
    $this->assertDatabaseMissing('role_user_relation', ['committee_dn' => $oldChildDn]);

    $this->assertDatabaseHas('role_group_relation', ['role_dn' => $newSourceRoleDn]);
    $this->assertDatabaseHas('role_group_relation', ['role_dn' => $newChildRoleDn]);
    $this->assertDatabaseMissing('role_group_relation', ['role_dn' => $oldSourceRoleDn]);
    $this->assertDatabaseMissing('role_group_relation', ['role_dn' => $oldChildRoleDn]);
});

test('app:move-committee moves a committee to the top level when no target is given', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $parent = TestLdap::makeCommittee($community, 'parent');
    $child = TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());

    $this->artisan('app:move-committee', [
        'community' => $uid,
        'committee' => 'child',
    ])->assertExitCode(0);

    $moved = Committee::findOrFail('ou=child,'.Committee::dnRoot($uid));
    expect($moved->getParentDn())->toBe("ou=Committees,ou=$uid,ou=Communities,".$moved->getBaseDn());
});

test('app:move-committee refuses to move a committee into its own descendant', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $parent = TestLdap::makeCommittee($community, 'parent');
    TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());

    $this->artisan('app:move-committee', [
        'community' => $uid,
        'committee' => 'parent',
        'target-committee' => 'child',
    ])->assertExitCode(1);
});

test('app:move-committee refuses a no-op move to the same parent', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $parent = TestLdap::makeCommittee($community, 'parent');
    TestLdap::makeCommittee($community, 'child', parentDn: $parent->getDn());

    $this->artisan('app:move-committee', [
        'community' => $uid,
        'committee' => 'child',
        'target-committee' => 'parent',
    ])->assertExitCode(1);
});

test('app:move-committee fails for an unknown committee', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeCommittee($community, 'target');

    $this->artisan('app:move-committee', [
        'community' => $uid,
        'committee' => 'does-not-exist',
        'target-committee' => 'target',
    ])->assertExitCode(1);
});
