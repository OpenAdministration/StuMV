<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\RoleMembership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

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
        if($this->option('date') === 'today()'){
            $date = today();
        }else{
            $date = Carbon::createFromFormat('Y-m-d', $this->option('date'));
        }
        $realms = Community::query()
            ->list() // only first level
            ->setDn(Community::$rootDn)
            ->search('ou', $this->argument('community'))
            ->get();
        foreach ($realms as $realm){
            $committees = Committee::fromCommunity($realm->getFirstAttribute('ou'))
                ->search('ou', $this->argument('committee'))
                ->get();
            foreach ($committees as $committee){
                $this->comment("> " . $committee->getDn());
                $roles = $committee->roles()
                    ->search('cn', $this->argument('role'))
                    ->get();
                foreach ($roles as $role){
                    /** @var Role $role */
                    $activeMemberships = RoleMembership::active($date)
                        ->where('committee_dn', $committee->getDn())
                        ->where('role_cn', $role->getFirstAttribute('cn'))
                        ->get();
                    $ldapMembers = $role->members();
                    // delete all members so far
                    $ldapMembers->detachAll();
                    $this->comment("  |-> " .$role->getDn());
                    foreach ($activeMemberships as $membership){
                        /** @var RoleMembership $membership */
                        // add only active members back
                        $this->comment("  |   |-> $membership->username");
                        $ldapMembers->attach($membership->user->ldap());
                    }
                }
            }
        }
    }
}
