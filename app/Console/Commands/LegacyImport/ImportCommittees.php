<?php

namespace App\Console\Commands\LegacyImport;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCommittees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import:committees';

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
        $gremien = DB::connection('opa00-legacy')
            ->table('gremium')
            ->orderBy('parent_gremium_id')
            ->orderBy('id')
            ->get();
        $realms = [];
        $committees = [];
        foreach ($gremien as $gremium){
            if(!isset($realms[$gremium->realm_uid])){
                $realms[$gremium->realm_uid] = Community::findByUid($gremium->realm_uid);
            }
            $this->comment('Importing Com ' . $gremium->name . ' to Realm ' . $gremium->realm_uid . ' ...');
            $d = Committee::findByName($gremium->realm_uid, $gremium->short);
            if(!$d?->exists()){
                $d = Committee::make([
                    'ou' => (string) $gremium->short,
                    'description' => mb_convert_encoding($gremium->name, 'UTF-8'),
                ]);
                dump(mb_convert_encoding($gremium->name, 'UTF-8'));
                //dump($d);
                if(!$gremium->parent_gremium_id){
                    $d->setDn('ou=' . $gremium->short . ',ou=Committees,' . $realms[$gremium->realm_uid]->getDn());
                }else{
                    $d->inside($committees[$gremium->parent_gremium_id]);
                }
                $d->save();
            }
            $committees[$gremium->id] = $d;
        }
    }
}
