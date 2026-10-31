<?php

namespace Database\Seeders;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use Illuminate\Database\Seeder;

/**
 * Maps the demo ref-finanzen group onto the Mailman list that
 * docker/mailman/seed.sh creates, so `php artisan mailman:sync-lists demo`
 * has something to do in the docker stack. The group's members come from the
 * three Referat Finanzen roles (role_group_relation), mirroring its LDAP
 * uniqueMembers in 20-demo.ldif.
 */
class DemoMailmanSeeder extends Seeder
{
    public function run(): void
    {
        $groupDn = Group::dnFrom('demo', 'ref-finanzen');
        $committeeDn = 'ou=Referat Finanzen,ou=StuRa,ou=Committees,ou=demo,ou=Communities,dc=stumv,dc=de';

        foreach (['haushaltsverantwortliche', 'kassenverantwortliche', 'revision'] as $roleCn) {
            GroupMembership::firstOrCreate([
                'group_dn' => $groupDn,
                'role_dn' => "cn=$roleCn,$committeeDn",
            ]);
        }

        GroupMailmanList::firstOrCreate([
            'realm' => 'demo',
            'group_dn' => $groupDn,
            'mailman_list_id' => 'finanzen.lists.demo.stumv.de',
        ]);
    }
}
