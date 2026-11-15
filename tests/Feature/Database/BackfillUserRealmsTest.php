<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Data shape of an instance still on main: every user row has realm = NULL
 * while role_user_relation.realm was just backfilled from committee_dn. The
 * composite FK added by 2026_07_20_000003 rejects that unless user realms are
 * backfilled first. FK checks are switched off to build the shape - a session
 * variable, unlike dropping the FK, which would commit the test transaction.
 */
uses(RefreshDatabase::class);

function legacyMembership(string $username, string $realm): void
{
    DB::table('role_user_relation')->insert([
        'role_cn' => 'mitglied',
        'committee_dn' => "ou=fsr,ou=Committees,ou=$realm,ou=Communities,dc=stumv,dc=de",
        'username' => $username,
        'realm' => $realm,
        'from' => today(),
    ]);
}

function runUserRealmBackfill(): void
{
    $migration = require database_path('migrations/2026_07_20_000003_add_composite_fk_to_role_user_relation_table.php');
    (fn () => $this->backfillUserRealms())->call($migration);
}

test('every membership gets a user row for its realm', function (): void {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    // one realm, one account in two realms, and a user without memberships
    DB::table('user')->insert([
        ['username' => 'single', 'full_name' => 'Single', 'email' => 'single@example.test', 'password' => 'x', 'uid' => 'guid-single', 'realm' => null],
        ['username' => 'shared', 'full_name' => 'Shared', 'email' => 'shared@example.test', 'password' => 'x', 'uid' => 'guid-shared', 'realm' => null],
        ['username' => 'idle', 'full_name' => 'Idle', 'email' => 'idle@example.test', 'password' => 'x', 'uid' => 'guid-idle', 'realm' => null],
    ]);
    legacyMembership('single', 'alpha');
    legacyMembership('shared', 'alpha');
    legacyMembership('shared', 'beta');
    legacyMembership('shared', 'beta');

    runUserRealmBackfill();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $orphans = DB::table('role_user_relation as r')
        ->leftJoin('user as u', fn ($join) => $join->on('u.username', '=', 'r.username')->on('u.realm', '=', 'r.realm'))
        ->whereNull('u.id')
        ->count();

    expect($orphans)->toBe(0)
        ->and(DB::table('user')->where('username', 'single')->pluck('realm')->all())->toBe(['alpha'])
        ->and(DB::table('user')->where('username', 'shared')->orderBy('realm')->pluck('realm')->all())->toBe(['alpha', 'beta'])
        // the original keeps its GUID, the copy is linked later by app:split-people-by-realm
        ->and(DB::table('user')->where('username', 'shared')->where('realm', 'alpha')->value('uid'))->toBe('guid-shared')
        ->and(DB::table('user')->where('username', 'shared')->where('realm', 'beta')->value('uid'))->toBeNull()
        ->and(DB::table('user')->where('username', 'idle')->value('realm'))->toBeNull();
});
