<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

class RegisterDirectoryApiClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directory-api:client
        {community : The short name (ou) of the community this client may query}
        {name : A label identifying the third-party application}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register a third-party application for the read-only directory API (client-credentials grant, bound to one community)';

    public function handle(ClientRepository $clients): int
    {
        $uid = $this->argument('community');

        if (! Community::findByUid($uid)) {
            $this->error("No community found with short name \"$uid\".");

            return self::FAILURE;
        }

        /** @var PassportClient $client */
        $client = $clients->createClientCredentialsGrantClient($this->argument('name'));
        $client->forceFill(['community_uid' => $uid])->save();

        $this->info('Client registered. Give these credentials to the third party (the secret is shown only once):');
        $this->line("  client_id:     {$client->id}");
        $this->line("  client_secret: {$client->plainSecret}");
        $this->line("  community:     $uid");
        $this->newLine();
        $this->comment('Request a token with: POST /oauth/token, grant_type=client_credentials, scope="committees groups users" (as needed).');

        return self::SUCCESS;
    }
}
