<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use Illuminate\Console\Command;

/**
 * Physically relocates a user's LDAP entry from one realm's People branch
 * into another - the same physical entry, not a copy (see CopyUserToRealm
 * for that). Only the DN's parent changes, not the RDN, so the entry's own
 * entryUUID is untouched by the move. Nothing else - not group uniqueMember
 * references, not the local users table - is touched.
 */
class MoveUserToRealm extends Command
{
    protected $signature = 'app:move-user-to-realm {uid} {from} {to}';

    protected $description = "Moves a user's LDAP entry from one realm's People branch into another.";

    public function handle(): int
    {
        $uid = (string) $this->argument('uid');

        $fromRealm = Community::findByUid((string) $this->argument('from'));

        if ($fromRealm === null) {
            $this->error("Realm \"{$this->argument('from')}\" does not exist.");

            return self::FAILURE;
        }

        $toRealm = Community::findByUid((string) $this->argument('to'));

        if ($toRealm === null) {
            $this->error("Realm \"{$this->argument('to')}\" does not exist.");

            return self::FAILURE;
        }

        if ($fromRealm->getShortCode() === $toRealm->getShortCode()) {
            $this->error('The source and destination realms are the same.');

            return self::FAILURE;
        }

        $user = LdapUser::query()->in($fromRealm->peopleDn())->where('uid', '=', $uid)->first();

        if ($user === null) {
            $this->error("$uid was not found in {$fromRealm->getShortCode()}'s People branch.");

            return self::FAILURE;
        }

        $this->comment("Moving $uid: {$user->getDn()} -> uid=$uid,{$toRealm->peopleDn()}");
        $user->move($toRealm->peopleDn());

        $this->info("Done. $uid moved from {$fromRealm->getShortCode()} to {$toRealm->getShortCode()}.");

        return self::SUCCESS;
    }
}
