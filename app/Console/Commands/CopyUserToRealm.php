<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\StripsLdapOperationalAttributes;
use App\Ldap\Community;
use App\Ldap\User;
use Illuminate\Console\Command;

/**
 * Gives a user an independent presence in another realm on demand - e.g. a
 * superadmin (App\Ldap\Community::ADMIN_REALM_UID) who only has an LDAP
 * entry in the dedicated admin realm, but also needs their own account in a
 * specific community. Same "independent clone, not a synced identity" shape
 * SplitPeopleByRealm already uses for a uid found in more than one realm -
 * the two accounts are never kept in sync afterwards.
 */
class CopyUserToRealm extends Command
{
    use StripsLdapOperationalAttributes;

    protected $signature = 'app:copy-user {uid} {from} {to} {--dry-run}';

    protected $description = "Copies a user's LDAP entry from one realm's People branch into another as an independent clone. Overwrites an existing entry already at the destination.";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
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

        $source = User::query()->in($fromRealm->peopleDn())->where('uid', '=', $uid)->first();

        if ($source === null) {
            $this->error("$uid was not found in {$fromRealm->getShortCode()}'s People branch.");

            return self::FAILURE;
        }

        $newDn = 'uid='.$uid.','.$toRealm->peopleDn();

        if ($newDn === $source->getDn()) {
            $this->error("$uid is already in {$toRealm->getShortCode()}.");

            return self::FAILURE;
        }

        $existing = User::query()->find($newDn);

        if ($existing !== null) {
            $this->comment("$newDn already exists, overwriting");

            if (! $dryRun) {
                $existing->delete();
            }
        }

        $this->comment("Copying $uid: {$source->getDn()} -> $newDn");

        if ($dryRun) {
            $this->comment('Dry run - no LDAP writes were made.');

            return self::SUCCESS;
        }

        $clone = new User($this->withoutOperationalAttributes($source->getAttributes()));
        $clone->setDn($newDn);
        $clone->save();

        $this->info("Done. $uid now exists independently in both {$fromRealm->getShortCode()} and {$toRealm->getShortCode()}.");

        return self::SUCCESS;
    }
}
