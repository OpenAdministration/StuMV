<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\RoleMembership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
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
            $date = Carbon::createFromFormat('Y-m-d', $this->option('date'));
        }

        $connection = Container::getDefaultConnection();
        $query = $connection->query();
        
        $realms = Community::query()
            ->setDn(Community::$rootDn)
            ->search('ou', $this->argument('community'))
            ->orderBy('ou')
            ->list()
            ->get(); 

        foreach ($realms as $realm) {
            $this->comment("> " . $realm->getFirstAttribute('ou'));
            
            $committees = Committee::fromCommunity($realm->getFirstAttribute('ou'))
                ->search('ou', $this->argument('committee'))
                ->get();
            foreach ($committees as $committee) {
                $this->comment("  |-> " . $committee->getDn());
                $roles = $committee->roles()
                    ->search('cn', $this->argument('role'))
                    ->get();
                foreach ($roles as $role) {
                    $activeMemberships = RoleMembership::active($date)
                        ->where('committee_dn', $committee->getDn())
                        ->where('role_cn', $role->getFirstAttribute('cn'))
                        ->get();
                    $this->comment("  |  |-> " . $role->getDn());

                    // delete all members so far
                    $currentMembers = $role->getAttribute('uniqueMember');
                    if (!in_array('', $currentMembers)) {
                        $query->add($role->getDn(), ['uniqueMember' => '']);
                    }
                    for ($i = 0; $i < count($currentMembers); $i++) {
                        if ($currentMembers[$i] !== '') {
                            $query->remove($role->getDn(), ['uniqueMember' => [ $currentMembers[$i] ]]);
                        }
                    }
                    
                    $ldapMembers = $role->members();
                    foreach ($activeMemberships as $membership){
                        /** @var RoleMembership $membership */
                        // add only active members back
                        $this->comment("  |  |  |-> $membership->username");
                        $ldapMembers->attach($membership->user->ldap());
                    }
                }
            }
        }
    }
}
