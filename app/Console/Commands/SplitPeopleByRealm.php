<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User;
use Illuminate\Console\Command;
use LdapRecord\Models\OpenLDAP\Group;

class SplitPeopleByRealm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:split-people-by-realm {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "One-time migration: moves every community's members out of the flat ou=People branch into their own realm-scoped ou=People branch, and relocates superadmins into a dedicated 'admin' realm. Accounts that turn out to belong to more than one realm become independent, cloned accounts - one per (username, realm) - rather than a single synced identity.";

    /**
     * The attributes an LDAP search never returns unless explicitly selected,
     * stripped defensively before cloning an entry so they're never sent back
     * to the server as if they were ordinary user attributes. memberof is a
     * dynlist-computed virtual attribute (see AddMemberOfAttributeScope,
     * applied to every App\Ldap\User query) - present whenever the source
     * entry belongs to any group, and rejected outright by the server if
     * ever included in an add.
     */
    private const array OPERATIONAL_ATTRIBUTES = [
        'entryuuid', 'entrycsn', 'creatorsname', 'createtimestamp',
        'modifiersname', 'modifytimestamp', 'structuralobjectclass',
        'subschemasubentry', 'hassubordinates', 'pwdchangedtime',
        'pwdaccountlockedtime', 'pwdfailuretime', 'pwdhistory', 'memberof',
    ];

    /**
     * uid => [realm-uid, ...] in the order each uid was found - the first
     * realm keeps the original physical entry (moved in place); every
     * subsequent realm gets its own independent clone. 'admin' is always
     * discovered first (see handle()), so a superadmin who also happens to
     * be a community member ends up with the original moved into the admin
     * realm and an independent clone left behind for the community - the
     * same "duplicate becomes independent accounts" rule applied uniformly.
     *
     * @var array<string, array<int, string>>
     */
    private array $realmsByUid = [];

    /**
     * realm-uid => [old flat dn => new realm-scoped dn], used to rewrite
     * that realm's admins/moderators uniqueMember values after their members
     * have been relocated.
     *
     * @var array<string, array<string, string>>
     */
    private array $dnUpdatesByRealm = [];

    /**
     * uid => attributes/dn of its one physical flat entry, fetched (and
     * removed as an option) at most once per uid.
     *
     * @var array<string, array{dn: string, attributes: array}>
     */
    private array $originals = [];

    private int $movedCount = 0;

    /**
     * uid => [realm-uid, ...] - every realm beyond the first for that uid.
     *
     * @var array<string, array<int, string>>
     */
    private array $clonedInto = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no LDAP writes will be made.');
        }

        $communities = Community::query()->get()->reject(fn (Community $c): bool => $c->isAdminRealm())->values();
        $communitiesByUid = $communities->keyBy(fn (Community $c): string => $c->getShortCode());

        $this->buildPersonIndex($communities);

        $this->ensureAdminRealmExists($dryRun);
        $this->processRealm(Community::ADMIN_REALM_UID, null, $dryRun);

        foreach ($communities as $community) {
            $this->processRealm($community->getShortCode(), $community, $dryRun);
        }

        $this->reportUnassigned();
        $this->reportSummary($dryRun);

        return self::SUCCESS;
    }

    private function ensureAdminRealmExists(bool $dryRun): void
    {
        if (Community::findByUid(Community::ADMIN_REALM_UID) !== null) {
            return;
        }

        $this->comment('> admin realm does not exist yet, creating it');

        if ($dryRun) {
            return;
        }

        $adminRealm = new Community(['ou' => Community::ADMIN_REALM_UID, 'description' => 'Superadmins']);
        $adminRealm->setDn('ou='.Community::ADMIN_REALM_UID.','.Community::rootDn());
        $adminRealm->generateSkeleton(full: false);
    }

    /**
     * Walks the super-admins group and every (non-admin) community's members
     * group exactly once, before mutating anything - a move() changes a DN a
     * later group might still reference otherwise.
     */
    private function buildPersonIndex($communities): void
    {
        $superAdminGroup = Group::query()->find($this->superAdminGroupDn());

        foreach ($superAdminGroup?->members()->get() ?? [] as $superAdmin) {
            $this->realmsByUid[$superAdmin->getFirstAttribute('uid')][] = Community::ADMIN_REALM_UID;
        }

        foreach ($communities as $community) {
            foreach ($this->membersGroupMembers($community) as $member) {
                $this->realmsByUid[$member->getFirstAttribute('uid')][] = $community->getShortCode();
            }
        }
    }

    private function processRealm(string $realmUid, ?Community $community, bool $dryRun): void
    {
        $this->comment("> $realmUid");

        $uids = $this->uidsAssignedTo($realmUid);

        if (empty($uids)) {
            $this->comment('  |-> no members to move');
        }

        foreach ($uids as $uid) {
            $this->moveOrCloneUid($uid, $realmUid, $community, $dryRun);
        }

        if ($community !== null) {
            $this->rewireRoleGroup($community->adminsGroup(), $realmUid, $dryRun);
            $this->rewireRoleGroup($community->moderatorsGroup(), $realmUid, $dryRun);
            $this->deleteMembersGroup($community, $dryRun);
        } else {
            $this->deleteSuperAdminGroup($dryRun);
        }
    }

    /**
     * @return array<int, string>
     */
    private function uidsAssignedTo(string $realmUid): array
    {
        return array_keys(array_filter(
            $this->realmsByUid,
            fn (array $realms): bool => in_array($realmUid, $realms, true)
        ));
    }

    private function moveOrCloneUid(string $uid, string $realmUid, ?Community $community, bool $dryRun): void
    {
        $realms = $this->realmsByUid[$uid];
        $isFirstRealmForUid = $realms[0] === $realmUid;

        $original = $this->originals[$uid] ??= $this->loadOriginal($uid);

        if ($original === null) {
            $this->warn("  ! $uid: referenced by a group but its LDAP entry is missing, skipping");

            return;
        }

        $newPeopleDn = $community !== null ? $community->peopleDn() : Community::peopleDnFor($realmUid);
        $newDn = 'uid='.$uid.','.$newPeopleDn;

        $this->dnUpdatesByRealm[$realmUid][$original['dn']] = $newDn;

        if ($isFirstRealmForUid) {
            $this->comment("  |-> moving $uid: {$original['dn']} -> $newDn");

            if (! $dryRun) {
                // move() takes the target *parent* container, not the full
                // new DN - it keeps the entry's own RDN (uid=...) as-is.
                User::findOrFailByUsername($uid)->move($newPeopleDn);
            }

            $this->movedCount++;
        } else {
            $this->comment("  |-> cloning $uid into an independent account: $newDn");

            if (! $dryRun) {
                $clone = new User($original['attributes']);
                $clone->setDn($newDn);
                $clone->save();
            }

            $this->clonedInto[$uid][] = $realmUid;
        }
    }

    /**
     * @return array{dn: string, attributes: array}|null
     */
    private function loadOriginal(string $uid): ?array
    {
        $entry = User::findByUsername($uid);

        if ($entry === null) {
            return null;
        }

        $attributes = array_diff_key($entry->getAttributes(), array_flip(self::OPERATIONAL_ATTRIBUTES));

        return ['dn' => $entry->getDn(), 'attributes' => $attributes];
    }

    private function rewireRoleGroup(?Group $group, string $realmUid, bool $dryRun): void
    {
        if ($group === null) {
            return;
        }

        $dnUpdates = $this->dnUpdatesByRealm[$realmUid] ?? [];
        $current = $group->getAttribute('uniqueMember') ?? [];

        $hasPlaceholder = in_array('', $current, true);
        $currentRealMembers = array_values(array_diff($current, ['']));

        $desired = [];
        foreach ($currentRealMembers as $dn) {
            if (isset($dnUpdates[$dn])) {
                $desired[] = $dnUpdates[$dn];

                continue;
            }

            $this->warn("  ! {$group->getFirstAttribute('cn')}: uniqueMember $dn was not found in this realm's members group - left unchanged, verify manually");
            $desired[] = $dn;
        }

        if ($desired === $currentRealMembers) {
            return;
        }

        foreach (array_diff($currentRealMembers, $desired) as $removed) {
            $this->comment("  |-> {$group->getFirstAttribute('cn')}: Remove: $removed");
        }
        foreach (array_diff($desired, $currentRealMembers) as $added) {
            $this->comment("  |-> {$group->getFirstAttribute('cn')}: Add: $added");
        }

        if ($hasPlaceholder || empty($desired)) {
            $desired[] = '';
        }

        if (! $dryRun) {
            $group->replaceAttribute('uniqueMember', $desired);
        }
    }

    private function deleteMembersGroup(Community $community, bool $dryRun): void
    {
        $group = $this->membersGroup($community);

        if ($group === null) {
            return;
        }

        $this->comment("  |-> deleting {$group->getDn()}");

        if (! $dryRun) {
            $group->delete();
        }
    }

    private function deleteSuperAdminGroup(bool $dryRun): void
    {
        $group = Group::query()->find($this->superAdminGroupDn());

        if ($group === null) {
            return;
        }

        $this->comment("  |-> deleting {$group->getDn()}");

        if (! $dryRun) {
            $group->delete();
        }
    }

    private function membersGroup(Community $community): ?Group
    {
        return Group::query()->in($community->getDn())->where('cn', '=', 'members')->first();
    }

    private function membersGroupMembers(Community $community)
    {
        return $this->membersGroup($community)?->members()->get() ?? collect();
    }

    private function superAdminGroupDn(): string
    {
        return 'cn=super-admins,'.config('ldap.connections.default.base_dn');
    }

    private function flatPeopleDn(): string
    {
        return 'ou=People,'.config('ldap.connections.default.base_dn');
    }

    private function reportUnassigned(): void
    {
        $flatUids = User::query()->in($this->flatPeopleDn())->list()->get()
            ->map(fn (User $user): string => $user->getFirstAttribute('uid'));

        $unassigned = $flatUids->reject(fn (string $uid): bool => isset($this->realmsByUid[$uid]))->values();

        if ($unassigned->isEmpty()) {
            return;
        }

        $this->comment('> unassigned (left in the flat ou=People branch, not a member of any community)');
        foreach ($unassigned as $uid) {
            $this->comment("  |-> $uid");
        }
    }

    private function reportSummary(bool $dryRun): void
    {
        $clonedCount = collect($this->clonedInto)->flatten()->count();

        $this->newLine();
        $this->comment($dryRun ? 'Dry run summary:' : 'Summary:');
        $this->comment("  Accounts moved: {$this->movedCount}");
        $this->comment("  Independent copies created: $clonedCount");

        foreach ($this->clonedInto as $uid => $realms) {
            $this->comment('    - '.$uid.': also independently created in '.implode(', ', $realms));
        }
    }
}
