<?php

namespace App\Console\Commands\LegacyImport;

use App\Ldap\Community;
use App\Ldap\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportUserFromLegacy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports the old legacy stuff to the new shiny one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = DB::connection('opa00-legacy')
            ->table('user')
            ->join('realm_assertion', 'user.id', '=', 'realm_assertion.user_id')
            ->get();

        $realms = [];

        foreach ($users as $user) {
            if (! isset($realms[$user->realm_uid])) {
                $realms[$user->realm_uid] = Community::findByUid($user->realm_uid);
            }
            $this->comment('Importing User '.$user->username.' to Realm '.$user->realm_uid.' ...');
            $name = explode(' ', (string) $user->fullName);
            $ldapUser = User::findByUsername($user->username);
            if (! $ldapUser?->exists()) {
                $ldapUser = User::make([
                    'uid' => $user->username,
                    'sn' => $name[1] ?? $user->fullName,
                    'givenName' => $name[0] ?? $user->fullName,
                    'cn' => $user->fullName,
                    'mail' => $user->email,
                    'userPassword' => $user->authKey, // something more or less random
                ]);
                $ldapUser->setDn('uid='.$user->username.',ou=People,dc=open-administration,dc=de');
                $ldapUser->save();
            }
            if (! $realms[$user->realm_uid]->membersGroup()->members()->exists($ldapUser)) {
                $realms[$user->realm_uid]->membersGroup()->members()->attach($ldapUser);
            }

            // The DB user row (used for login sessions, not LDAP auth
            // itself) doesn't exist yet for a legacy user importing for the
            // first time - create it same as ImportUsersFromUniLdap does,
            // but only set the login-irrelevant password/verification once.
            $dbUser = \App\Models\User::firstOrNew(['username' => $user->username]);
            $dbUser->full_name = $user->fullName;
            $dbUser->email = $user->email;
            $dbUser->realm = $user->realm_uid;
            if (! $dbUser->exists) {
                $dbUser->password = Hash::make(Str::uuid());
                $dbUser->email_verified_at = now();
            }
            $dbUser->save();
        }
    }
}
