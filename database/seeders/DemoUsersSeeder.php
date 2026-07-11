<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the DB-side records for the six demo logins that live in LDAP
 * (docker/openldap/bootstrap/20-demo.ldif). Emails match the LDAP `mail`
 * attribute, so the LDAP auth provider's `sync_existing` (email => mail)
 * reconciles onto these rows on first login instead of creating duplicates.
 *
 * All accounts share the LDAP password "Demo-password1"; the hash stored here
 * is only a placeholder (sync_passwords is false — LDAP remains the source of
 * truth for authentication). Their email addresses are pre-verified.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'demo-hhv',      'full_name' => 'Hanna Haushalt'],
            ['username' => 'demo-kv',       'full_name' => 'Karl Kasse'],
            ['username' => 'demo-revision', 'full_name' => 'Rita Revision'],
            ['username' => 'demo-stura',    'full_name' => 'Stefan Studierendenrat'],
            ['username' => 'demo-fsr',      'full_name' => 'Frank Fachschaft'],
            ['username' => 'demo-studi',    'full_name' => 'Sara Studentin'],
        ];

        foreach ($users as $u) {
            User::firstOrNew(['username' => $u['username']])
                ->forceFill([
                    'full_name' => $u['full_name'],
                    'email' => $u['username'] . '@demo.stumv.de',
                    'email_verified_at' => now(),
                    'password' => Hash::make('Demo-password1'),
                    'realm' => 'demo',
                    'domain' => 'demo.stumv.de',
                ])
                ->save();
        }
    }
}
