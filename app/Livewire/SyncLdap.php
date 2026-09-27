<?php

namespace App\Livewire;

use App\Console\Commands\Concerns\DiffsUniqueMembers;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SyncLdap extends Component
{
    use DiffsUniqueMembers;

    #[Locked]
    public string $uid;

    public array $preview = ['roles' => [], 'groups' => []];

    /**
     * $uid is passed as a plain string (not the Community model) precisely
     * so it can be #[Locked] - re-resolving and re-checking the admin
     * ability here, rather than trusting the header's @can check alone, is
     * what actually prevents a moderator of one realm from syncing another
     * realm's LDAP state via a crafted Livewire request.
     */
    public function mount(string $uid): void
    {
        $realm = Community::findByOrFail('ou', $uid);
        abort_unless(auth()->user()->can('admin', $realm), 403);

        $this->uid = $uid;
    }

    public function render()
    {
        return view('livewire.sync-ldap');
    }

    /**
     * Builds a read-only preview of the pending changes (scoped to this
     * realm, filtered to only the committees/roles/groups that would
     * actually change) and opens the confirmation modal. The real sync
     * still runs through the full ldap:sync-roles/ldap:sync-groups commands
     * below, so this preview never itself writes to LDAP.
     */
    public function openSyncPreview(): void
    {
        $realm = Community::findByOrFail('ou', $this->uid);

        $roleChanges = $this->buildRoleChanges($realm);
        $groupChanges = $this->buildGroupChanges($realm);
        $membersByDn = $this->resolveMembers($realm, [...$roleChanges, ...$groupChanges]);

        $this->preview = [
            'roles' => $this->withResolvedMembers($roleChanges, $membersByDn),
            'groups' => $this->withResolvedMembers($groupChanges, $membersByDn),
        ];

        Flux::modal('sync-ldap-preview')->show();
    }

    public function syncLdap()
    {
        $rolesExitCode = Artisan::call('ldap:sync-roles', ['community' => $this->uid]);
        $groupsExitCode = Artisan::call('ldap:sync-groups', ['community' => $this->uid]);

        Flux::modal('sync-ldap-preview')->close();

        if ($rolesExitCode === 0 && $groupsExitCode === 0) {
            Flux::toast(variant: 'success', text: __('sync.ldap_success'));
        }
    }

    /**
     * Additions/removals are still raw member DNs at this point - resolved
     * to display names/links afterwards by resolveMembers()/withResolvedMembers(),
     * since removals can't be named from RoleMembership (they're no longer
     * an active membership, that's precisely why they're being removed).
     *
     * @return array<int, array{committee: string, committee_ou: string, role: string, role_cn: string, additions: array<int, string>, removals: array<int, string>}>
     */
    private function buildRoleChanges(Community $realm): array
    {
        $memberships = RoleMembership::active(today())->where('realm', $this->uid)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);
        $ldapUsersByUsername = $this->ldapUsersByUsername($realm, $memberships);

        $changes = [];
        foreach (Committee::fromCommunity($this->uid)->get() as $committee) {
            foreach ($committee->roles()->get() as $role) {
                $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');
                $roleMemberships = $membershipsByRole->get($key, collect());

                $desiredDns = $roleMemberships
                    ->map(fn (RoleMembership $m) => $ldapUsersByUsername->get($m->username)?->getDn())
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $diff = $this->diffUniqueMembers($role, $desiredDns);
                if (empty($diff['additions']) && empty($diff['removals'])) {
                    continue;
                }

                $changes[] = [
                    'committee' => $committee->getFullName(),
                    'committee_ou' => $committee->getShortName(),
                    'role' => $role->getFirstAttribute('description') ?? $role->getFirstAttribute('cn'),
                    'role_cn' => $role->getFirstAttribute('cn'),
                    'additions' => $diff['additions'],
                    'removals' => $diff['removals'],
                ];
            }
        }

        return $changes;
    }

    /**
     * @return array<int, array{group: string, additions: array<int, string>, removals: array<int, string>}>
     */
    private function buildGroupChanges(Community $realm): array
    {
        $memberships = RoleMembership::active(today())->where('realm', $this->uid)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);
        $groupRolesByGroup = GroupMembership::all()->groupBy('group_dn');
        $ldapUsersByUsername = $this->ldapUsersByUsername($realm, $memberships);

        $changes = [];
        foreach (Group::query()->in(Group::dnRoot($this->uid))->get() as $group) {
            $groupRoles = $groupRolesByGroup->get($group->getDn(), collect());

            $desiredDns = collect();
            foreach ($groupRoles as $groupRole) {
                $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                $key = $committeeDn.'|'.$roleCn;

                foreach ($membershipsByRole->get($key, collect()) as $membership) {
                    if ($dn = $ldapUsersByUsername->get($membership->username)?->getDn()) {
                        $desiredDns->push($dn);
                    }
                }
            }

            $diff = $this->diffUniqueMembers($group, $desiredDns->unique()->values()->all());
            if (empty($diff['additions']) && empty($diff['removals'])) {
                continue;
            }

            $changes[] = [
                'group' => $group->getFirstAttribute('cn'),
                'additions' => $diff['additions'],
                'removals' => $diff['removals'],
            ];
        }

        return $changes;
    }

    /**
     * @param  Collection<int, RoleMembership>  $memberships
     * @return Collection<string, LdapUser>
     */
    private function ldapUsersByUsername(Community $realm, Collection $memberships): Collection
    {
        $usernames = $memberships->pluck('username')->unique()->all();

        return empty($usernames)
            ? collect()
            : LdapUser::query()->in($realm->peopleDn())->whereIn('uid', $usernames)->get()
                ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));
    }

    /**
     * Resolves every member DN referenced by the given change entries to a
     * display name and username (for linking to their profile), in a single
     * batched LDAP lookup. The uid is parsed straight out of each DN (rather
     * than only relying on already-known usernames from RoleMembership) so
     * removed members - who by definition no longer have an active
     * membership - still get a name instead of falling back to their raw DN.
     *
     * @param  array<int, array{additions: array<int, string>, removals: array<int, string>}>  $entries
     * @return Collection<string, array{name: string, username: ?string}> DN => member info
     */
    private function resolveMembers(Community $realm, array $entries): Collection
    {
        $dns = collect($entries)->flatMap(fn (array $entry): array => [...$entry['additions'], ...$entry['removals']])->unique()->values();
        $uidsByDn = $dns->mapWithKeys(fn (string $dn): array => [$dn => $this->extractUid($dn)]);
        $uids = $uidsByDn->filter()->unique()->values()->all();

        $usersByUid = empty($uids)
            ? collect()
            : LdapUser::query()->in($realm->peopleDn())->whereIn('uid', $uids)->get()
                ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

        return $uidsByDn->map(function (?string $uid, string $dn) use ($usersByUid): array {
            $user = $uid !== null ? $usersByUid->get($uid) : null;

            return [
                'name' => $user?->getFirstAttribute('cn') ?? $uid ?? $dn,
                'username' => $uid,
            ];
        });
    }

    private function extractUid(string $dn): ?string
    {
        return preg_match('/^uid=([^,]+)/i', $dn, $matches) ? $matches[1] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  Collection<string, array{name: string, username: ?string}>  $membersByDn
     * @return array<int, array<string, mixed>>
     */
    private function withResolvedMembers(array $entries, Collection $membersByDn): array
    {
        $fallback = fn (string $dn): array => ['name' => $dn, 'username' => null];

        return array_map(fn (array $entry): array => [
            ...$entry,
            'additions' => array_map(fn (string $dn): array => $membersByDn->get($dn, $fallback($dn)), $entry['additions']),
            'removals' => array_map(fn (string $dn): array => $membersByDn->get($dn, $fallback($dn)), $entry['removals']),
        ], $entries);
    }
}
