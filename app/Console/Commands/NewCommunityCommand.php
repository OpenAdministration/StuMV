<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use Illuminate\Console\Command;

class NewCommunityCommand extends Command
{
    protected $signature = 'community:new {uid}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $community = Community::make([
            'ou' => $this->argument('uid'),
        ]);
        $community->setDn("ou={$this->argument('uid')},".Community::rootDn());
        $community->generateSkeleton();
    }
}
