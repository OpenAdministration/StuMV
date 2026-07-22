<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsUniqueMembers;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\RoleMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class LdapSyncRoles extends Command
{
    use SyncsUniqueMembers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:sync-roles
                {community? : The short name to search for of the community}
                {committee? : The short name to search for of the committee}
                {role? : The short name to search for of the role}
                {--date=today() : The date to sync for in Y-m-d format e.g. 2025-12-31}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs the active roles from the Database to LDAP';

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

        // Fetch every active membership in one query - instead of one query
        // per role/user encountered while walking the realm/committee/role
        // tree below. LDAP users are resolved per-realm instead (see the
        // loop below): usernames are only unique within one realm now, so a
        // single global uid lookup could resolve the wrong realm's account
        // and sync the wrong person into a security-relevant LDAP role.
        $memberships = RoleMembership::active($date)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

        $realms = Community::query()
            ->setDn(Community::$rootDn)->search()
            ->list()
            ->get();

        if ($this->argument('community') !== null) {
            $realms = $realms->filter(fn (Community $realm): bool => $realm->getFirstAttribute('ou') === $this->argument('community'))->values();
        }

        foreach ($realms as $realm) {
            $realmUid = $realm->getFirstAttribute('ou');
            $this->comment('> '.$realmUid);

            $usernamesInRealm = $memberships->where('realm', $realmUid)->pluck('username')->unique()->all();
            $ldapUsersByUsername = empty($usernamesInRealm)
                ? collect()
                : LdapUser::query()->in($realm->peopleDn())->whereIn('uid', $usernamesInRealm)->get()
                    ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

            $committees = Committee::fromCommunity($realmUid)
                ->searchFor('ou', $this->argument('committee'))
                ->get();
            foreach ($committees as $committee) {
                $this->comment('  |-> '.$committee->getDn());
                $roles = $committee->roles()
                    ->searchFor('cn', $this->argument('role'))
                    ->get();
                foreach ($roles as $role) {
                    $this->comment('  |   |-> '.$role->getDn());

                    $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');
                    $roleMemberships = $membershipsByRole->get($key, collect());

                    $desiredDns = $roleMemberships
                        ->map(function (RoleMembership $membership) use ($ldapUsersByUsername) {
                            $user = $ldapUsersByUsername->get($membership->username);
                            if ($user === null) {
                                $this->warn("  |   |   |-> Unknown LDAP user: $membership->username");
                            }

                            return $user?->getDn();
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    $this->syncUniqueMembers($role, $desiredDns, '  |   |   |-> ');
                }
            }
        }
    }
}
