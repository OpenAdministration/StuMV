<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Tests\Support\TestLdap;

/**
 * app:split-people-by-realm is the one-time migration that converts the old,
 * pre-split directory shape (one flat ou=People branch, membership expressed
 * via each community's own cn=members group) into the new one (a per-realm
 * ou=People branch, membership is the physical location itself). The local
 * dev/test seed data (docker/openldap/bootstrap/*.ldif) is already in the
 * *post-split* shape - there is no more "before" state left locally to run
 * the real command against - so this test builds that legacy shape directly
 * (flat entries + a hand-built cn=members group) rather than relying on the
 * seed data, and is the durable regression check for this command.
 */
uses(RefreshDatabase::class);

/** A flat, pre-split-shaped person under the legacy ou=People branch. */
function legacyPerson(string $uid): LdapUser
{
    return TestLdap::makeUser($uid);
}

/** Hand-build a community's old cn=members group (retired by Community::generateSkeleton() now) and attach legacy people to it. */
function attachLegacyMembers(Community $community, array $users): Group
{
    $group = new Group(['cn' => 'members', 'uniqueMember' => '']);
    $group->setDn('cn=members,'.$community->getDn());
    $group->save();
    TestLdap::track($group);

    foreach ($users as $user) {
        TestLdap::attach($group, $user);
    }

    return $group;
}

/**
 * In the real (pre-split) directory this group already exists - created
 * once, out-of-band, at initial bootstrap (slapadd, which runs as root and
 * bypasses ACLs entirely) - the app's own service-account bind never
 * creates it, only ever reads/deletes it (which the existing ACLs do
 * allow). Creating a brand-new top-level entry directly under the base DN
 * at runtime correctly requires "write to parent", which that bind
 * legitimately doesn't have - so this fixture bootstraps it the same way
 * production did, via the directory's root DN, not the app's own connection.
 */
function legacySuperAdminGroup(): Group
{
    $dn = 'cn=super-admins,'.config('ldap.connections.default.base_dn');
    $group = Group::query()->find($dn);

    if ($group === null) {
        $connection = config('ldap.connections.default');
        // Needs the ldap:// scheme, not just "host:port" - modern libldap
        // (PHP's ldap extension calls through to ldap_initialize()) rejects
        // a bare host:port string with "Bad parameter to an ldap routine"
        // instead of the older, more lenient legacy parsing.
        $conn = ldap_connect("ldap://{$connection['hosts'][0]}:{$connection['port']}");
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_bind($conn, 'cn=Administration,'.$connection['base_dn'], 'admin-not-production');
        ldap_add($conn, $dn, [
            'objectClass' => ['groupOfUniqueNames', 'top'],
            'cn' => 'super-admins',
            'uniqueMember' => [''],
        ]);
        ldap_unbind($conn);

        $group = Group::query()->find($dn);
        TestLdap::track($group);
    }

    return $group;
}

test('a dry run reports the plan without moving anything', function (): void {
    $community = newCommunity();
    $single = legacyPerson('legacyone'.bin2hex(random_bytes(3)));
    attachLegacyMembers($community, [$single]);

    $oldDn = $single->getDn();

    $this->artisan('app:split-people-by-realm', ['--dry-run' => true])->assertExitCode(0);

    expect(LdapUser::findByUsername($single->getFirstAttribute('uid'))->getDn())->toBe($oldDn);
});

test('a single-realm member is moved into that realm\'s own People branch', function (): void {
    $community = newCommunity();
    $single = legacyPerson('legacyone'.bin2hex(random_bytes(3)));
    attachLegacyMembers($community, [$single]);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $moved = LdapUser::findByUsername($single->getFirstAttribute('uid'));
    expect($moved)->not->toBeNull()
        ->and($moved->getDn())->toEndWith(','.$community->peopleDn());
});

test('an account that is a member of two realms becomes two independent accounts', function (): void {
    $communityA = newCommunity();
    $communityB = newCommunity();
    $shared = legacyPerson('legacytwo'.bin2hex(random_bytes(3)));
    $uid = $shared->getFirstAttribute('uid');
    $originalGuid = LdapUser::findByUsername($uid)->getConvertedGuid();

    attachLegacyMembers($communityA, [$shared]);
    attachLegacyMembers($communityB, [$shared]);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $inA = LdapUser::query()->in($communityA->peopleDn())->where('uid', '=', $uid)->first();
    $inB = LdapUser::query()->in($communityB->peopleDn())->where('uid', '=', $uid)->first();

    expect($inA)->not->toBeNull()
        ->and($inB)->not->toBeNull()
        ->and($inA->getConvertedGuid())->not->toBe($inB->getConvertedGuid());

    // Exactly one of the two is the original entry (moved in place); the
    // other is a brand-new, independent clone.
    $guids = [$inA->getConvertedGuid(), $inB->getConvertedGuid()];
    expect($guids)->toContain($originalGuid);
});

test('a stale entry already sitting at the clone destination is overwritten instead of causing an error', function (): void {
    $communityA = newCommunity();
    $communityB = newCommunity();
    $shared = legacyPerson('legacystale'.bin2hex(random_bytes(3)));
    $uid = $shared->getFirstAttribute('uid');

    attachLegacyMembers($communityA, [$shared]);
    attachLegacyMembers($communityB, [$shared]);

    // A leftover from e.g. an interrupted prior run - same uid, different
    // attributes, already sitting exactly where this run's clone into
    // communityB needs to write.
    $stale = new LdapUser([
        'uid' => $uid,
        'cn' => 'Stale Leftover',
        'sn' => 'Leftover',
        'givenName' => 'Stale',
        'mail' => $uid.'@stale.test',
        'userPassword' => '{ARGON2}'.password_hash('Aa1!'.bin2hex(random_bytes(6)), PASSWORD_ARGON2ID),
    ]);
    $stale->setDn('uid='.$uid.','.$communityB->peopleDn());
    $stale->save();
    TestLdap::track($stale);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $inB = LdapUser::query()->in($communityB->peopleDn())->where('uid', '=', $uid)->first();

    expect($inB)->not->toBeNull()
        ->and($inB->getFirstAttribute('cn'))->toBe($shared->getFirstAttribute('cn'));
});

test('admins/moderators uniqueMember values are rewritten to the new location', function (): void {
    $community = newCommunity();
    $admin = legacyPerson('legacyadmin'.bin2hex(random_bytes(3)));
    attachLegacyMembers($community, [$admin]);
    TestLdap::attach($community->adminsGroup(), $admin);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $moved = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $admin->getFirstAttribute('uid'))->first();

    expect($community->adminsGroup()->members()->get()->map(fn ($m) => $m->getDn())->all())
        ->toBe([$moved->getDn()]);
});

test('the community\'s members group is deleted after the split', function (): void {
    $community = newCommunity();
    $member = legacyPerson('legacythree'.bin2hex(random_bytes(3)));
    attachLegacyMembers($community, [$member]);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    expect(Group::query()->find('cn=members,'.$community->getDn()))->toBeNull();
});

test('a superadmin is moved into the dedicated admin realm', function (): void {
    $superAdmin = legacyPerson('legacysuper'.bin2hex(random_bytes(3)));
    TestLdap::attach(legacySuperAdminGroup(), $superAdmin);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $adminRealm = Community::findOrFailByUid(Community::ADMIN_REALM_UID);
    TestLdap::track($adminRealm);
    $moved = LdapUser::findByUsername($superAdmin->getFirstAttribute('uid'));

    expect($moved->getDn())->toEndWith(','.$adminRealm->peopleDn())
        ->and($moved->isSuperAdmin())->toBeTrue();
});

test('a superadmin who is also a community member ends up as two independent accounts', function (): void {
    $community = newCommunity();
    $dual = legacyPerson('legacydual'.bin2hex(random_bytes(3)));
    $uid = $dual->getFirstAttribute('uid');

    attachLegacyMembers($community, [$dual]);
    TestLdap::attach(legacySuperAdminGroup(), $dual);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    $adminRealm = Community::findOrFailByUid(Community::ADMIN_REALM_UID);
    TestLdap::track($adminRealm);

    $inAdminRealm = LdapUser::query()->in($adminRealm->peopleDn())->where('uid', '=', $uid)->first();
    $inCommunity = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $uid)->first();

    expect($inAdminRealm)->not->toBeNull()
        ->and($inCommunity)->not->toBeNull()
        ->and($inAdminRealm->getConvertedGuid())->not->toBe($inCommunity->getConvertedGuid());
});

test('a community predating the per-realm People branch gets one created before members are moved into it', function (): void {
    $community = newCommunity();
    OrganizationalUnit::query()->find($community->peopleDn())->delete();

    $single = legacyPerson('legacynopeople'.bin2hex(random_bytes(3)));
    attachLegacyMembers($community, [$single]);

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    expect(OrganizationalUnit::query()->find($community->peopleDn()))->not->toBeNull();

    $moved = LdapUser::findByUsername($single->getFirstAttribute('uid'));
    expect($moved)->not->toBeNull()
        ->and($moved->getDn())->toEndWith(','.$community->peopleDn());
});

test('an account in no community\'s members group is left unassigned in the flat branch', function (): void {
    $unassigned = legacyPerson('legacyunassigned'.bin2hex(random_bytes(3)));
    $oldDn = $unassigned->getDn();

    $this->artisan('app:split-people-by-realm')->assertExitCode(0);

    expect(LdapUser::findByUsername($unassigned->getFirstAttribute('uid'))->getDn())->toBe($oldDn);
});
