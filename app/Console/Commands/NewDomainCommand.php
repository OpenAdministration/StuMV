<?php

namespace App\Console\Commands;

use App\Ldap\Domain;
use Illuminate\Console\Command;

class NewDomainCommand extends Command
{
    protected $signature = 'community:domain:new {community-uid} {fqdn}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $d = Domain::make([
            'dc' => $this->argument('fqdn'),
        ]);
        $d->setDn("dc={$this->argument('fqdn')},".Domain::dnRoot($this->argument('community-uid')));
        $d->save();
    }
}
