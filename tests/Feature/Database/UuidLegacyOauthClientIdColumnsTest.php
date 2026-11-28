<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Legacy (v1, Passport 11) oauth_* tables with integer client ids, on a
 * throwaway connection so the DDL stays out of the test database.
 */
beforeEach(function (): void {
    config([
        'database.connections.legacy_oauth' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'passport.connection' => 'legacy_oauth',
    ]);

    $schema = Schema::connection('legacy_oauth');
    $schema->create('oauth_clients', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });
    foreach (['oauth_access_tokens', 'oauth_auth_codes'] as $name) {
        $schema->create($name, function (Blueprint $table): void {
            $table->string('id', 100)->primary();
            $table->unsignedInteger('client_id');
        });
    }

    DB::connection('legacy_oauth')->table('oauth_clients')->insert(['id' => 5, 'name' => 'StuFiS']);
    DB::connection('legacy_oauth')->table('oauth_access_tokens')->insert(['id' => 'token', 'client_id' => 5]);
});

function runUuidLegacyMigration(): void
{
    (require database_path('migrations/2026_07_18_000001_uuid_legacy_oauth_client_id_columns.php'))->up();
}

test('legacy integer client ids become strings and keep matching', function (): void {
    runUuidLegacyMigration();

    $db = DB::connection('legacy_oauth');
    expect(Schema::connection('legacy_oauth')->getColumnType('oauth_clients', 'id'))->toBe('varchar')
        ->and(Schema::connection('legacy_oauth')->getColumnType('oauth_access_tokens', 'client_id'))->toBe('varchar')
        ->and($db->table('oauth_clients')->value('id'))->toBe('5')
        ->and($db->table('oauth_access_tokens')->where('client_id', '5')->exists())->toBeTrue();

    $uuid = (string) Str::uuid();
    $db->table('oauth_clients')->insert(['id' => $uuid, 'name' => 'Neu']);
    expect($db->table('oauth_clients')->where('id', $uuid)->exists())->toBeTrue();
});

test('string client ids are left alone', function (): void {
    runUuidLegacyMigration();
    runUuidLegacyMigration();

    expect(DB::connection('legacy_oauth')->table('oauth_clients')->value('id'))->toBe('5');
});
