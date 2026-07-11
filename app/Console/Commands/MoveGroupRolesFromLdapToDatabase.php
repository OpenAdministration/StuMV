<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMembership;
use Illuminate\Console\Command;

class MoveGroupRolesFromLdapToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:move-group-roles-from-ldap-to-database
                {community? : The short name to search for of the community}
                {group? : The short name to search for of the group}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $realms = Community::query()
            ->list() // only first level
            ->setDn(Community::$rootDn)->search()
            ->get();

        foreach ($realms as $realm) {
            $groups = Group::fromCommunity($realm->getFirstAttribute('ou'))
                ->searchFor('ou', $this->argument('group'))
                ->get();

            foreach ($groups as $group) {
                $this->comment('> '.$group->getDn());

                // get roles
                $roles = $group->members()->get();

                foreach ($roles as $role) {
                    $this->comment($role);
                    GroupMembership::create([
                        'group_dn' => $group->getDn(),
                        'role_dn' => $role,
                    ]);
                }
            }
        }
    }
}
