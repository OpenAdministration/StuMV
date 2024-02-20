<?php

namespace App\Console\Commands\LegacyImport;

use App\Ldap\Community;
use App\Ldap\Domain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import:domains';

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
        $domains = DB::connection('opa00-legacy')
            ->table('domain')
            ->get();
        $realms = [];
        foreach ($domains as $domain){
            if(!isset($realms[$domain->realm_uid])){
                $realms[$domain->realm_uid] = Community::findByUid($domain->realm_uid);
            }
            $this->comment('Importing Domain ' . $domain->name . ' to Realm ' . $domain->realm_uid . ' ...');
            $d = Domain::make([
                'dc' => $domain->name,
            ]);
            $d->setDn('dc=' . $domain->name . ',ou=Domains,' . $realms[$domain->realm_uid]->getDn());
            $d->save();
        }

    }
}
