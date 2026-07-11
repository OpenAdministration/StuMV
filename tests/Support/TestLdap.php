<?php

namespace Tests\Support;

use App\Ldap\Community;
use App\Ldap\SuperUserGroup;
use App\Ldap\User as LdapUser;
use App\Models\User;
use LdapRecord\Models\Model as LdapModel;

/**
 * Builds throwaway, fully wired test users on the fly.
 *
 * Authorization in this app is decided against LDAP group membership (see
 * CommunityPolicy / UserPolicy), so a usable acting-as user needs three things
 * kept in sync: an LDAP entry under ou=People, membership in the right LDAP
 * group(s), and a database User whose `username` matches the LDAP `uid` (that is
 * what App\Models\User::ldap() resolves on).
 *
 * Everything created here is registered for teardown and removed by the global
 * afterEach hook in tests/Pest.php, so tests never leak directory state. The
 * seeded "demo" community (docker/openldap/bootstrap/20-demo.ldif) is used as
 * the default home community because it already ships the full group + committee
 * + role skeleton the feature tests exercise.
 */
class TestLdap
{
    /** @var list<LdapUser> LDAP users to delete on teardown. */
    private static array $users = [];

    /** @var list<array{group: LdapModel, user: LdapUser}> memberships to detach on teardown. */
    private static array $memberships = [];

    /** Create a fresh LDAP person with a unique uid. */
    public static function makeUser(?string $uid = null): LdapUser
    {
        $uid ??= 'testusr'.bin2hex(random_bytes(4));
        LdapUser::findByUsername($uid)?->delete();

        $ldap = new LdapUser([
            'uid' => $uid,
            'cn' => 'Test '.$uid,
            'sn' => 'User',
            'givenName' => 'Test',
            'mail' => $uid.'@example.test',
            'userPassword' => '{ARGON2}'.password_hash('Aa1!'.bin2hex(random_bytes(6)), PASSWORD_ARGON2ID),
        ]);
        $ldap->setDn("uid=$uid,ou=People,{base}");
        $ldap->save();

        self::$users[] = $ldap;

        return $ldap;
    }

    /** Add the LDAP user to a group and remember it for teardown. */
    public static function attach(LdapModel $group, LdapUser $user): void
    {
        $group->members()->attach($user);
        self::$memberships[] = ['group' => $group, 'user' => $user];
    }

    /** Create the matching database user that actingAs() drives. */
    public static function databaseUser(LdapUser $ldap): User
    {
        return User::factory()->create([
            'username' => $ldap->getFirstAttribute('uid'),
            'full_name' => $ldap->getFirstAttribute('cn'),
            'email' => $ldap->getFirstAttribute('mail'),
        ]);
    }

    public static function member(string $community): User
    {
        $ldap = self::makeUser();
        self::attach(Community::findByUid($community)->membersGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function moderator(string $community): User
    {
        $ldap = self::makeUser();
        self::attach(Community::findByUid($community)->membersGroup(), $ldap);
        self::attach(Community::findByUid($community)->moderatorsGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function admin(string $community): User
    {
        $ldap = self::makeUser();
        self::attach(Community::findByUid($community)->membersGroup(), $ldap);
        self::attach(Community::findByUid($community)->adminsGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function superAdmin(): User
    {
        $ldap = self::makeUser();
        self::attach(SuperUserGroup::group(), $ldap);

        return self::databaseUser($ldap);
    }

    /** Detach every membership and delete every user created during the test. */
    public static function cleanup(): void
    {
        foreach (self::$memberships as $membership) {
            try {
                $membership['group']->members()->detach($membership['user']);
            } catch (\Throwable) {
                // group/user may already be gone; ignore.
            }
        }
        foreach (self::$users as $user) {
            try {
                $user->delete();
            } catch (\Throwable) {
                // already deleted; ignore.
            }
        }
        self::$memberships = [];
        self::$users = [];
    }
}
