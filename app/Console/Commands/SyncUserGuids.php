<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class SyncUserGuids extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:sync-user-guids
        {community? : The short name of the realm to restrict this to}
        {--dry-run}';

    /**
     * @var string
     */
    protected $description = "Fixes App\Models\User.uid (the cached LDAP entryUUID) when it no longer matches the user's real LDAP entry";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no database writes will be made.');
        }

        $query = User::query()->whereNotNull('realm');

        if ($this->argument('community') !== null) {
            $query->where('realm', $this->argument('community'));
        }

        $checked = 0;
        $fixed = 0;
        $unknownRealm = 0;
        $noLdapEntry = 0;
        $communities = [];

        foreach ($query->orderBy('realm')->orderBy('username')->get() as $user) {
            $checked++;

            $community = $communities[$user->realm] ??= Community::findByUid($user->realm);

            if (! $community) {
                $this->warn("  ! {$user->realm}/{$user->username}: unknown realm");
                $unknownRealm++;

                continue;
            }

            $ldapUser = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $user->username)->first();

            if (! $ldapUser) {
                $this->warn("  ! {$user->realm}/{$user->username}: no matching LDAP entry");
                $noLdapEntry++;

                continue;
            }

            $currentGuid = $ldapUser->getConvertedGuid();

            if ($currentGuid === $user->uid) {
                continue;
            }

            $this->comment("  |-> {$user->realm}/{$user->username}: {$user->uid} -> {$currentGuid}");
            $fixed++;

            if ($dryRun) {
                continue;
            }

            try {
                // uid isn't mass-assignable (see App\Models\User::$fillable).
                $user->forceFill(['uid' => $currentGuid])->save();
            } catch (QueryException $exception) {
                $this->warn("  ! {$user->realm}/{$user->username}: could not update uid - {$exception->getMessage()}");
            }
        }

        $this->newLine();
        $this->comment("Checked: $checked, fixed: $fixed, unknown realm: $unknownRealm, no LDAP entry: $noLdapEntry");

        return self::SUCCESS;
    }
}
