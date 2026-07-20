<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsUniqueMembers;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class LdapSyncGroups extends Command
{
    use SyncsUniqueMembers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:sync-groups
                {community? : The short name to search for of the community}
                {--date=today() : The date to sync for in Y-m-d format e.g. 2025-12-31}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs the active groups from the Database to LDAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('date') === 'today()') {
            $date = today();
        } else {
            $date = Date::createFromFormat('Y-m-d', $this->option('date'));
        }

        // Fetch every active membership and every group/role mapping in one
        // query each - instead of one query per group/role mapping
        // encountered while walking the realm/group tree below. LDAP users
        // are resolved per-realm instead (see the loop below): usernames are
        // only unique within one realm now, so a single global uid lookup
        // could resolve the wrong realm's account and sync the wrong person
        // into a security-relevant LDAP group.
        $memberships = RoleMembership::active($date)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

        $groupRolesByGroup = GroupMembership::all()->groupBy('group_dn');

        $realms = Community::query()
            ->setDn(Community::$rootDn)->search()
            ->list()
            ->get();

        foreach ($realms as $realm) {
            $realmUid = $realm->getFirstAttribute('ou');
            $this->comment('> '.$realmUid);

            $usernamesInRealm = $memberships->where('realm', $realmUid)->pluck('username')->unique()->all();
            $ldapUsersByUsername = empty($usernamesInRealm)
                ? collect()
                : LdapUser::query()->in($realm->peopleDn())->whereIn('uid', $usernamesInRealm)->get()
                    ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

            $groups = Group::query()->in(Group::dnRoot($realmUid))->get();
            foreach ($groups as $group) {
                $this->comment('  |-> '.$group->getDn());

                $groupRoles = $groupRolesByGroup->get($group->getDn(), collect());

                $desiredDns = collect();
                foreach ($groupRoles as $groupRole) {
                    $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                    $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                    $key = $committeeDn.'|'.$roleCn;

                    $roleMemberships = $membershipsByRole->get($key, collect());
                    foreach ($roleMemberships as $membership) {
                        $user = $ldapUsersByUsername->get($membership->username);
                        if ($user === null) {
                            $this->warn("  |   |-> Unknown LDAP user: $membership->username");

                            continue;
                        }
                        $desiredDns->push($user->getDn());
                    }
                }

                $this->syncUniqueMembers($group, $desiredDns->unique()->values()->all(), '  |   |-> ');
            }
        }
    }
}
