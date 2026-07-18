<?php

namespace Database\Seeders;

use App\Ldap\Committee;
use App\Models\RoleMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the DB side of the demo community that lives in LDAP
 * (docker/openldap/bootstrap/20-demo.ldif):
 *
 *  1. The six demo login rows. Emails match the LDAP `mail` attribute, so the
 *     LDAP auth provider's `sync_existing` (email => mail) reconciles onto
 *     these rows on first login instead of creating duplicates. All accounts
 *     share the LDAP password "Demo-password1"; the hash stored here is only a
 *     placeholder (sync_passwords is false — LDAP remains the source of truth
 *     for authentication). Their email addresses are pre-verified.
 *
 *  2. The committee role memberships (table role_user_relation). Per the
 *     LDAP+DB split, role *definitions* live in LDAP while *who holds a role,
 *     and when* lives in the DB — the committee/role UI reads its members from
 *     here. Targets are derived from each role's LDAP `uniqueMember`, so the
 *     stored committee_dn / role_cn always match what the app queries at
 *     runtime.
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
                    'email' => $u['username'].'@demo.stumv.de',
                    'email_verified_at' => now(),
                    'password' => Hash::make('Demo-password1'),
                    'realm' => 'demo',
                    'domain' => 'demo.stumv.de',
                ])
                ->save();
        }

        // Active since the start of the last winter semester (open-ended).
        $from = Date::create(2025, 10, 1);

        foreach (Committee::fromCommunity('demo')->get() as $committee) {
            foreach ($committee->roles()->get() as $role) {
                $cn = $role->getFirstAttribute('cn');
                $committeeDn = $role->getParentDn();

                foreach ((array) ($role->uniqueMember ?: []) as $memberDn) {
                    // Skip the empty placeholder member new roles are created with.
                    if (! str_starts_with((string) $memberDn, 'uid=')) {
                        continue;
                    }

                    $username = explode(',', substr((string) $memberDn, 4), 2)[0];

                    RoleMembership::firstOrCreate(
                        [
                            'role_cn' => $cn,
                            'committee_dn' => $committeeDn,
                            'username' => $username,
                        ],
                        [
                            'from' => $from,
                            'until' => null,
                            'decided' => $from,
                            'comment' => 'Demo seed',
                        ]
                    );
                }
            }
        }
    }
}
