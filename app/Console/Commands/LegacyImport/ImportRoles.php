<?php

namespace App\Console\Commands\LegacyImport;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\RoleMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import:roles';

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
        $roles = DB::connection('opa00-legacy')
            ->table('role')
            ->select([
                'role.id',
                'role.name as name',
                'role.short as rshort',
                'realm_uid',
                'gremium.short as gshort',
                'gremium_id'
            ])
            ->join('gremium', 'gremium.id', '=', 'role.gremium_id')
            ->get();
        $realms = [];
        $committees = [];
        foreach ($roles as $role){
            if(!isset($realms[$role->realm_uid])){
                $realms[$role->realm_uid] = Community::findByUid($role->realm_uid);
            }
            $this->comment('Importing Com ' . $role->name . ' to Realm ' . $role->realm_uid . ' ...');

            $c = Committee::findByName($role->realm_uid, $role->gshort);
            $r = $c?->roles()->where('cn', $role->rshort);
            if(!$r?->exists()){
                $r = Role::make([
                    'cn' => $role->rshort,
                    'description' => mb_convert_encoding($role->name, 'UTF-8'),
                    'uniqueMember' => ''
                ]);
                dump(mb_convert_encoding($role->name, 'UTF-8'));
                $r->inside($c);
                $r->save();
            }

            $assertions = DB::connection('opa00-legacy')
                ->table('role_assertion')
                ->join('user', 'user_id', '=', 'user.id')
                ->where('role_id', $role->id)
                ->get();

            foreach ($assertions as $assertion){
                RoleMembership::create([
                    'role_cn' => $role->rshort,
                    'committee_dn' => $c->getDn(),
                    'username' => $assertion->username,
                    'from' => $assertion->from,
                    'until' => $assertion->until,
                ]);

            }
        }
    }
}
