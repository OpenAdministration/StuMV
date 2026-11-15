<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use App\Support\DatabaseUserProvisioner;
use Illuminate\Console\Command;

class ProvisionDatabaseUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:provision-database-users
        {community? : The short name of the realm to restrict this to}
        {--dry-run : Only report which accounts are missing a user row or are linked to the wrong LDAP entry}';

    /**
     * @var string
     */
    protected $description = 'Creates the missing user rows for LDAP accounts that never signed in, so they can be added to roles';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no database writes will be made.');
        }

        $communities = $this->argument('community') !== null
            ? array_filter([Community::findByUid((string) $this->argument('community'))])
            : Community::query()->get()->all();

        if ($communities === []) {
            $this->error("Realm \"{$this->argument('community')}\" does not exist.");

            return self::FAILURE;
        }

        $missing = 0;
        $unlinked = 0;

        foreach ($communities as $community) {
            $realmUid = $community->getShortCode();

            foreach (LdapUser::query()->in($community->peopleDn())->get() as $ldapUser) {
                $username = (string) $ldapUser->getFirstAttribute('uid');
                $row = User::where('username', $username)->where('realm', $realmUid)->first();

                if ($row === null) {
                    $this->comment("  |-> $realmUid/$username: no user row");
                    $missing++;
                } elseif ($row->uid !== $ldapUser->getConvertedGuid()) {
                    $this->comment("  |-> $realmUid/$username: linked to another LDAP entry");
                    $unlinked++;
                } else {
                    continue;
                }

                if (! $dryRun) {
                    DatabaseUserProvisioner::ensure($community, $ldapUser);
                }
            }
        }

        $verb = $dryRun ? 'Would fix' : 'Fixed';
        $this->info("$verb $missing missing and $unlinked mislinked user row(s).");

        return self::SUCCESS;
    }
}
