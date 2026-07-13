<?php

use App\Ldap\User as LdapUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['database.connections.opa00-legacy' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]]);

    Schema::connection('opa00-legacy')->create('user', function ($table): void {
        $table->id();
        $table->string('username');
        $table->string('fullName');
        $table->string('email');
        $table->string('authKey');
    });

    Schema::connection('opa00-legacy')->create('realm_assertion', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('realm_uid');
    });
});

test('legacy:import:users records the realm on the database user entry', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    // Pre-create the LDAP user so the command takes its "already exists"
    // branch: creating a brand new LDAP entry here hits an unrelated,
    // pre-existing bug (the command hardcodes a stale
    // "dc=open-administration,dc=de" base DN that doesn't exist in this - or
    // any current - directory), which is out of scope for this fix.
    $ldapUser = TestLdap::makeUser('legacyusr');
    TestLdap::attach($community->membersGroup(), $ldapUser);

    DB::connection('opa00-legacy')->table('user')->insert([
        'username' => 'legacyusr',
        'fullName' => 'Legacy Person',
        'email' => 'legacyusr@example.test',
        'authKey' => 'somekey',
    ]);
    DB::connection('opa00-legacy')->table('realm_assertion')->insert([
        'user_id' => 1,
        'realm_uid' => $uid,
    ]);

    $this->artisan('legacy:import:users')->assertExitCode(0);

    $this->assertDatabaseHas('user', [
        'username' => 'legacyusr',
        'realm' => $uid,
    ]);
});
