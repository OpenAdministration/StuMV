<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use LdapRecord\Container;

class LdapSyncGroups extends Command
{
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
            $date = Carbon::createFromFormat('Y-m-d', $this->option('date'));
        }

        $connection = Container::getDefaultConnection();
        $query = $connection->query();

        $realms = Community::query()
            ->setDn(Community::$rootDn)
            ->search('ou', $this->argument('community'))
            ->list()
            ->get();        

        foreach ($realms as $realm) {
            $this->comment("> " . $realm->getFirstAttribute('ou'));
            
            $groups = Group::query()->in(Group::dnRoot($realm->getFirstAttribute('ou')))->get();
            foreach ($groups as $group) {
                $this->comment("  |-> " . $group->getDn());

                $currentMembers = $group->getAttribute('uniqueMember');
                $newMembers = [];
                $roles = GroupMembership::where('group_dn', $group->getDn())->get();
                foreach ($roles as $role) {
                    $roleCn = str_replace('cn=', '', substr($role->role_dn, 0, strpos($role->role_dn, ',')));
                    $committeeDn = strstr($role->role_dn, "ou=");
                    $activeMemberships = RoleMembership::active($date)
                        ->where('committee_dn', $committeeDn)
                        ->where('role_cn', $roleCn)
                        ->get();
                    foreach ($activeMemberships as $membership) {
                        $newMembers[] = $membership->user->ldap();
                    }
                }
                $newMembers = array_unique($newMembers);

                $membersToRemove = array_diff($currentMembers, $newMembers);
                foreach ($membersToRemove as $memberToRemove) {
                    if ($memberToRemove !== '') {
                        $this->comment("  |  |-> Remove: $memberToRemove");
                        $query->remove($group->getDn(), ['uniqueMember' => [ $memberToRemove ]]);
                    }
                }

                $membersToAdd = array_diff($newMembers, $currentMembers);
                $ldapMembers = $group->users();
                foreach ($membersToAdd as $memberToAdd) {
                    $this->comment("  |  |-> Add: $memberToAdd");
                    $ldapMembers->attach($memberToAdd);
                }
            }
        }
    }
}
