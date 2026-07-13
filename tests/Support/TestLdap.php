<?php

namespace Tests\Support;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\SuperUserGroup;
use App\Ldap\User as LdapUser;
use App\Models\User;
use LdapRecord\Models\Model as LdapModel;

/**
 * Builds throwaway, fully wired LDAP fixtures on the fly.
 *
 * Authorization in this app is decided against LDAP group membership (see
 * CommunityPolicy / UserPolicy), so a usable acting-as user needs three things
 * kept in sync: an LDAP entry under ou=People, membership in the right LDAP
 * group(s), and a database User whose `username` matches the LDAP `uid` (that is
 * what App\Models\User::ldap() resolves on).
 *
 * On top of users this also builds directory *structure* — whole community
 * skeletons, committees, roles and groups — so tests can create exactly the
 * shape they need instead of coupling to the hand-seeded demo/testcom data.
 *
 * Everything created here is registered for teardown and removed by the global
 * afterEach hook in tests/Pest.php, so tests never leak directory state.
 */
class TestLdap
{
    /** @var list<LdapUser> LDAP users to delete on teardown. */
    private static array $users = [];

    /** @var list<array{group: LdapModel, user: LdapUser}> memberships to detach on teardown. */
    private static array $memberships = [];

    /** @var list<LdapModel> structural entries (communities/committees/roles/groups) to delete on teardown. */
    private static array $entries = [];

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

    public static function member(string|Community $community): User
    {
        $community = self::resolveCommunity($community);
        $ldap = self::makeUser();
        self::attach($community->membersGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function moderator(string|Community $community): User
    {
        $community = self::resolveCommunity($community);
        $ldap = self::makeUser();
        self::attach($community->membersGroup(), $ldap);
        self::attach($community->moderatorsGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function admin(string|Community $community): User
    {
        $community = self::resolveCommunity($community);
        $ldap = self::makeUser();
        self::attach($community->membersGroup(), $ldap);
        self::attach($community->adminsGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    public static function superAdmin(): User
    {
        $ldap = self::makeUser();
        self::attach(SuperUserGroup::group(), $ldap);

        return self::databaseUser($ldap);
    }

    /** A member of $community, and a moderator of just $committee (not the community itself). */
    public static function committeeModerator(Committee $committee, Community $community): User
    {
        $ldap = self::makeUser();
        self::attach($community->membersGroup(), $ldap);
        self::attach($committee->moderatorsGroup(), $ldap);

        return self::databaseUser($ldap);
    }

    /**
     * Create a full community skeleton (Groups/Committees/Domains OUs plus the
     * admins/moderators/members groups), just like NewRealm does.
     */
    public static function makeCommunity(?string $uid = null): Community
    {
        $uid ??= 'tcom'.bin2hex(random_bytes(4));

        $community = new Community([
            'ou' => $uid,
            'description' => 'Test Community '.$uid,
        ]);
        $community->setDn("ou=$uid,ou=Communities,".$community->getBaseDn());
        $community->generateSkeleton();

        // Delete last (shortest DN) so its children are already gone; recursive
        // delete mops up the skeleton OUs/groups regardless.
        self::$entries[] = $community;

        return $community;
    }

    /** Create a committee under the community (optionally nested under $parentDn). */
    public static function makeCommittee(Community $community, ?string $ou = null, string $parentDn = ''): Committee
    {
        $ou ??= 'com'.bin2hex(random_bytes(3));
        $uid = $community->getFirstAttribute('ou');

        $committee = new Committee([
            'ou' => $ou,
            'description' => 'Committee '.$ou,
        ]);
        $committee->setDn(Committee::dnFrom($uid, $ou, parentDn: $parentDn));
        $committee->save();

        array_unshift(self::$entries, $committee);

        return $committee;
    }

    /** Create a role inside a committee. */
    public static function makeRole(Committee $committee, ?string $cn = null): Role
    {
        $cn ??= 'role'.bin2hex(random_bytes(3));

        $role = new Role([
            'cn' => $cn,
            'description' => 'Role '.$cn,
            'uniqueMember' => '',
        ]);
        $role->inside($committee);
        $role->save();

        array_unshift(self::$entries, $role);

        return $role;
    }

    /**
     * Register an entry created outside the factory (e.g. by the component under
     * test) so it is torn down with the rest.
     */
    public static function track(LdapModel $entry): void
    {
        array_unshift(self::$entries, $entry);
    }

    /** Create a realm-level group under ou=Groups. */
    public static function makeGroup(Community $community, ?string $cn = null): Group
    {
        $cn ??= 'grp'.bin2hex(random_bytes(3));
        $uid = $community->getFirstAttribute('ou');

        $group = new Group([
            'cn' => $cn,
            'description' => 'Group '.$cn,
            'uniqueMember' => '',
        ]);
        $group->setDn("cn=$cn,ou=Groups,ou=$uid,ou=Communities,".$group->getBaseDn());
        $group->save();

        array_unshift(self::$entries, $group);

        return $group;
    }

    /** Detach every membership and delete every entry created during the test. */
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
            self::forget($user);
        }
        // Deepest entries first; recursive delete removes any remaining children.
        foreach (self::$entries as $entry) {
            self::forget($entry, recursive: true);
        }

        self::$memberships = [];
        self::$users = [];
        self::$entries = [];
    }

    private static function resolveCommunity(string|Community $community): Community
    {
        return $community instanceof Community ? $community : Community::findByUid($community);
    }

    private static function forget(LdapModel $entry, bool $recursive = false): void
    {
        try {
            $entry->delete(recursive: $recursive);
        } catch (\Throwable) {
            // already deleted; ignore.
        }
    }
}
