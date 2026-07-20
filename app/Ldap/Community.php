<?php

namespace App\Ldap;

use App\Ldap\Traits\HasRelationships;
use App\Ldap\Traits\SearchScopeTrait;
use LdapRecord\Laravel\ImportableFromLdap;
use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Query\Model\Builder;

/***
 * @property $ou
 * @property $description
 */
class Community extends OrganizationalUnit implements LdapImportable
{
    use HasRelationships;
    use ImportableFromLdap;
    use SearchScopeTrait;

    public static string $rootDn = 'ou=Communities,{base}';

    /**
     * The dedicated realm superadmins live under - not a "real" community:
     * no admins/moderators/committees/domains/API or OIDC clients of its
     * own, see generateSkeleton(). Its members get moderator/admin/
     * superadmin rights everywhere via CommunityPolicy instead.
     */
    public const ADMIN_REALM_UID = 'admin';

    public static function rootDn()
    {
        // would be nice if we could substitute a bit more elegant
        return 'ou=Communities,'.config('ldap.connections.default.base_dn');
    }

    public static function findByUid(string $uid): ?self
    {
        return self::query()
            ->whereEquals('ou', $uid)
            ->first();
    }

    public static function findOrFailByUid(string $uid): self
    {
        return self::findByUid($uid) ?? abort(404);
    }

    /**
     * Resolves the login URL for a realm uid, falling back to the generic
     * realm picker if it's absent or no longer exists - shared by every
     * place that sends a user back to a login page (post-logout, session
     * timeout, forced logout) without necessarily having an Authenticatable
     * to hand.
     */
    public static function loginUrlFor(?string $uid): string
    {
        if ($uid && self::findByUid($uid)) {
            return route('realm.login', ['realm' => $uid]);
        }

        return route('login');
    }

    public function getShortCode()
    {
        return $this->ou[0];
    }

    public function getLongName()
    {
        return $this->description[0] ?? '';
    }

    public static function peopleDnFor(string $uid): string
    {
        return "ou=People,ou=$uid,".self::rootDn();
    }

    public function peopleDn(): string
    {
        return 'ou=People,'.$this->getDn();
    }

    public function isAdminRealm(): bool
    {
        return $this->getShortCode() === self::ADMIN_REALM_UID;
    }

    /**
     * Derives the realm a specific physical LDAP entry belongs to directly
     * from its own DN - a physical entry lives under at most one community's
     * People branch, so no query is needed. Returns null for an entry that
     * isn't (yet) placed under any community's People branch (e.g. still in
     * the flat legacy ou=People, or the superadmin-only "admin" realm).
     */
    public static function membershipFor(User $ldapUser): ?string
    {
        if (preg_match('/^uid=[^,]+,ou=People,ou=([0-9A-Za-z_\-]+),'.self::rootDn().'$/', (string) $ldapUser->getDn(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('limitResults', static function (Builder $builder): void {
            // ->list() restricts this to direct (one-level) children of
            // ou=Communities - without it, a subtree search also matches
            // every nested OU (each community's own People/Groups/Committees/
            // Domains branches, and every sub-committee within them) as if
            // they were communities in their own right.
            $builder->in(self::$rootDn)
                ->list()
                ->where('ou', '!=', 'Communities');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ou';
    }

    public function moderatorsGroup(): Group
    {
        return Group::query()->in($this->getDn())->where('cn', 'moderators')->first();
    }

    public function adminsGroup(): Group
    {
        return Group::query()->in($this->getDn())->where('cn', 'admins')->first();
    }

    /**
     * @param  bool  $full  Whether to also create Groups/Committees/Domains
     *                      and the admins/moderators groups. The dedicated
     *                      "admin" superadmin realm needs none of that - it
     *                      only ever holds People, and its members already
     *                      get moderator/admin/superadmin rights everywhere
     *                      via CommunityPolicy, not through its own groups.
     */
    public function generateSkeleton(bool $full = true)
    {
        $this->save();

        $ous = $full
            ? ['People' => 'The People', 'Groups' => 'The Groups', 'Committees' => 'The Committees', 'Domains' => 'The Domains']
            : ['People' => 'The People'];

        foreach ($ous as $ouName => $ouDescription) {
            $ou = new OrganizationalUnit([
                'ou' => $ouName,
                'description' => $ouDescription,
            ]);
            $ou->setDn("ou=$ouName,".$this->getDn());
            $ou->save();
        }

        if (! $full) {
            return;
        }

        foreach (['admins', 'moderators'] as $gName) {
            $g = new Group([
                'cn' => $gName,
                'uniqueMember' => '',
            ]);
            $g->setDn("cn=$gName,".$this->getDn());
            $g->save();
        }
    }
}
