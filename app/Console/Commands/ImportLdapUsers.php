<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportLdapUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:import-ldap-users
        {community? : The short name of the realm to restrict this to}
        {--verified : Mark the imported email addresses as verified}
        {--dry-run}';

    /**
     * @var string
     */
    protected $description = 'Creates the missing App\Models\User rows for LDAP people entries that never logged in (counterpart of app:sync-user-guids)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Running in --dry-run mode - no database writes will be made.');
        }

        $communities = $this->argument('community') !== null
            ? [Community::findByUid($this->argument('community'))]
            : Community::query()->get()->all();

        if ($communities === [null]) {
            $this->error('Unknown realm: '.$this->argument('community'));

            return self::FAILURE;
        }

        $checked = 0;
        $imported = 0;
        $skipped = 0;

        foreach ($communities as $community) {
            $realm = $community->getShortCode();

            foreach (LdapUser::query()->in($community->peopleDn())->get() as $ldapUser) {
                $checked++;
                $username = $ldapUser->getFirstAttribute('uid');
                $guid = $ldapUser->getConvertedGuid();

                $existing = User::where('uid', $guid)
                    ->orWhere(fn ($query) => $query->where('username', $username)->where('realm', $realm))
                    ->first();

                if ($existing?->uid === $guid && $existing->realm === $realm) {
                    continue;
                }

                if ($existing !== null) {
                    $hint = $existing->uid === $guid
                        ? "its entryUUID belongs to {$existing->realm}/{$existing->username}"
                        : 'a row with a different entryUUID exists - run app:sync-user-guids';
                    $this->warn("  ! {$realm}/{$username}: skipped, {$hint}");
                    $skipped++;

                    continue;
                }

                $this->comment("  |-> {$realm}/{$username}");
                $imported++;

                if ($dryRun) {
                    continue;
                }

                // uid isn't mass-assignable (see App\Models\User::$fillable).
                (new User)->forceFill([
                    'uid' => $guid,
                    'username' => $username,
                    'full_name' => $ldapUser->getFirstAttribute('cn'),
                    'email' => $ldapUser->getFirstAttribute('mail'),
                    'email_verified_at' => $this->option('verified') ? now() : null,
                    // LDAP stays the source of truth for authentication.
                    'password' => Hash::make(Str::random(40)),
                    'realm' => $realm,
                ])->save();
            }
        }

        $this->newLine();
        $this->comment("Checked: $checked, imported: $imported, skipped: $skipped");

        return self::SUCCESS;
    }
}
