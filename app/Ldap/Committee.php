<?php

namespace App\Ldap;

use App\Ldap\Traits\SearchScopeTrait;
use App\Models\User;
use Illuminate\Support\Arr;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\DistinguishedNameBuilder;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Query\Model\Builder;

class Committee extends OrganizationalUnit
{
    use SearchScopeTrait;

    public static function dnFrom(string $uid, string $ou, ?array $parent_ous = null, string $parentDn = '')
    {
        // if dn is given short circuit the method
        if (! empty($parentDn)) {
            return "ou=$ou,".$parentDn;
        }
        // standardize input
        $parent_ous ??= [];
        $parents = implode(',ou=', $parent_ous);

        return "ou=$ou,".$parents.self::dnRoot($uid);
    }

    public static function scopeFromCommunity(Builder $query, string $uid): Builder
    {
        return $query->in(self::dnRoot($uid))
            ->whereNotEquals('ou', 'Committees');
    }

    public static function dnRoot(string $uid)
    {
        return "ou=Committees,ou=$uid,ou=Communities,{base}";
    }

    /**
     * Same as dnRoot(), but with {base} substituted for the connection's
     * real base DN - needed to compare against plain-string DN columns in
     * the database (e.g. role_user_relation.committee_dn), which store the
     * fully resolved DN, never the literal "{base}" placeholder.
     */
    public static function dnRootResolved(string $uid): string
    {
        return "ou=Committees,ou=$uid,".Community::rootDn();
    }

    public function setDnFrom(string $uid, string|array $ous): static
    {
        $dn = self::dnFrom($uid, $ous);

        return parent::setDn($dn);
    }

    public function parentCommittee(): ?Committee
    {
        $dn = DistinguishedName::make($this->getDn());
        $parentDn = $dn->parent();
        if (! str_contains((string) $parentDn, ',ou=Committees,')) {
            return null;
        }

        return self::findOrFail($parentDn);
    }

    public function getFullName(): string
    {
        return $this->getFirstAttribute('description');
    }

    public function getShortName(): string
    {
        return $this->getFirstAttribute('ou');
    }

    /**
     * @return array returns all ou's inside the ou=Committees path starting with the uppermost Entry
     */
    public function committeePath(): array
    {
        $dn = new DistinguishedNameBuilder($this->getDn());
        $ous = $dn->pop(5); // only real parents are left
        $ous = $ous->components();

        return array_reverse(Arr::map($ous, fn ($entry) => $entry[1]));
    }

    /**
     * @return array returns all ou's inside the ou=Committees path starting with the uppermost Entry but without itself
     */
    public function parentCommitteePath(): array
    {
        return array_slice($this->committeePath(), -1);
    }

    /**
     * @return Builder returns a querry wich
     */
    public function roles(): Builder
    {
        return Role::query()
            ->list()
            ->setBaseDn($this->getDn())
            ->whereNotEquals('cn', 'moderators');
    }

    /**
     * The hidden LDAP group backing this committee's own moderators, scoped
     * to this committee and its descendants (see hasModerator()). Unlike
     * regular roles, membership is direct LDAP group membership - no
     * RoleMembership/date-range tracking, mirroring Community::moderatorsGroup().
     * Self-heals (creates the group on first access) so committees that
     * existed before this feature shipped don't need a manual backfill.
     */
    public function moderatorsGroup(): Group
    {
        // ->list() restricts this to a direct (one-level) child of this
        // committee's own DN - without it, the default subtree scope would
        // also match a *descendant* committee's own "cn=moderators" group,
        // since committees can be nested arbitrarily deep.
        $group = Group::query()->in($this->getDn())->list()->where('cn', 'moderators')->first();

        if ($group === null) {
            $group = new Group([
                'cn' => 'moderators',
                'uniqueMember' => '',
            ]);
            $group->setDn('cn=moderators,'.$this->getDn());
            $group->save();
        }

        return $group;
    }

    /**
     * Whether $user is a direct member of this committee's own moderators
     * group - unlike hasModerator(), this does not walk ancestors. Callers
     * that already know an ancestor's status (e.g. while walking a tree
     * top-down) should use this to check just the one additional level
     * instead of re-walking the whole chain via hasModerator().
     */
    public function isDirectModerator(User $user): bool
    {
        return $this->moderatorsGroup()->members()->exists($user->ldap());
    }

    /**
     * Whether $user moderates this committee - either directly (member of
     * this committee's own moderators group) or by moderating an ancestor
     * committee, since a committee-moderator's authority extends to the
     * committee they were assigned plus all of its descendants.
     */
    public function hasModerator(User $user): bool
    {
        $current = $this;

        while ($current !== null) {
            if ($current->isDirectModerator($user)) {
                return true;
            }

            $current = $current->parentCommittee();
        }

        return false;
    }

    public static function findByName(string $uid, string $name): ?self
    {
        return self::fromCommunity($uid)->where('ou', $name)->first();
    }

    public static function findByNameOrFail(string|Community $community, string $name): self
    {
        if ($community instanceof Community) {
            $uid = $community->getFirstAttribute('ou');
        } else {
            $uid = $community;
        }

        return self::fromCommunity($uid)->where('ou', $name)->first() ?? abort(404);
    }
}
