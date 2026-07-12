<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use LdapRecord\Container;

class LdapSyncRoles extends Command
{
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

        $connection = Container::getDefaultConnection();
        $query = $connection->query();

        $realms = Community::query()
            ->setDn(Community::$rootDn)->search()
            ->list()
            ->get();

        foreach ($realms as $realm) {
            $this->comment('> '.$realm->getFirstAttribute('ou'));

            $committees = Committee::fromCommunity($realm->getFirstAttribute('ou'))
                ->searchFor('ou', $this->argument('committee'))
                ->get();
            foreach ($committees as $committee) {
                $this->comment('  |-> '.$committee->getDn());
                $roles = $committee->roles()
                    ->searchFor('cn', $this->argument('role'))
                    ->get();
                foreach ($roles as $role) {
                    $this->comment('  |  |-> '.$role->getDn());

                    $currentMembers = $role->getAttribute('uniqueMember');

                    $activeMemberships = RoleMembership::active($date)
                        ->where('committee_dn', $committee->getDn())
                        ->where('role_cn', $role->getFirstAttribute('cn'))
                        ->get();
                    $newMembers = [];
                    foreach ($activeMemberships as $membership) {
                        /** @var RoleMembership $membership */
                        $newMembers[] = $membership->user->ldap();
                    }
                    $newMembers = array_unique($newMembers);

                    $membersToRemove = array_diff($currentMembers, $newMembers);
                    foreach ($membersToRemove as $memberToRemove) {
                        if ($memberToRemove !== '') {
                            $this->comment("  |  |  |-> Remove: $memberToRemove");
                            $query->remove($role->getDn(), ['uniqueMember' => [$memberToRemove]]);
                        }
                    }

                    $membersToAdd = array_diff($newMembers, $currentMembers);
                    $ldapMembers = $role->members();
                    foreach ($membersToAdd as $memberToAdd) {
                        $this->comment("  |  |  |-> Add: $memberToAdd");
                        $ldapMembers->attach($memberToAdd);
                    }
                }
            }
        }
    }
}
