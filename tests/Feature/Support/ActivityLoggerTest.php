<?php

use App\Ldap\Group;
use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

test('the acting account is recorded by its entryUUID', function (): void {
    $user = new User(['username' => 'alice', 'realm' => 'demo']);
    $user->forceFill(['id' => 1, 'uid' => '1b0f7a3e-6a8b-4f4d-9c55-0d7c5e3b6f10']);
    $this->actingAs($user);

    $log = resolve(ActivityLogger::class)->record('domain.created', 'demo');

    expect($log->actor_type)->toBe('user')
        ->and($log->actor_id)->toBe('1b0f7a3e-6a8b-4f4d-9c55-0d7c5e3b6f10')
        ->and($log->realm)->toBe('demo')
        ->and($log->action)->toBe('domain.created');
});

test('without a logged in account the system is the actor', function (): void {
    $log = resolve(ActivityLogger::class)->record('account.deleted', 'demo');

    expect($log->actor_type)->toBeNull()
        ->and($log->actor_id)->toBeNull()
        ->and($log->ip)->toBeNull();
});

test('an identity provider acts instead of the logged in account', function (): void {
    $user = new User(['username' => 'alice', 'realm' => 'demo']);
    $user->forceFill(['id' => 1, 'uid' => '1b0f7a3e-6a8b-4f4d-9c55-0d7c5e3b6f10']);
    $this->actingAs($user);
    $provider = makeIdentityProvider('demo');

    $log = resolve(ActivityLogger::class)->record('role_membership.created', 'demo', actor: $provider);

    expect($log->actor_type)->toBe('identity_provider')
        ->and($log->actor_id)->toBe($provider->id);
});

test('a database row is recorded by its key and the given label', function (): void {
    $domain = new Domain;
    $domain->forceFill(['id' => 42]);

    $log = resolve(ActivityLogger::class)->record('domain.deleted', null, $domain, label: 'example.org');

    expect($log->subject_type)->toBe($domain->getMorphClass())
        ->and($log->subject_id)->toBe('42')
        ->and($log->subject_label)->toBe('example.org');
});

test('an LDAP entry is recorded by its entryUUID and named after its description', function (): void {
    $group = (new Group)->setRawAttributes([
        'entryuuid' => ['5d2c9a41-3f0e-4a7b-8e6d-2b1c0f9e8a76'],
        'cn' => ['vorstand'],
        'description' => ['Vorstand'],
    ]);

    $log = resolve(ActivityLogger::class)->record('group.created', 'demo', $group);

    expect($log->subject_type)->toBe(Group::class)
        ->and($log->subject_id)->toBe('5d2c9a41-3f0e-4a7b-8e6d-2b1c0f9e8a76')
        ->and($log->subject_label)->toBe('Vorstand');
});

test('passwords, secrets and tokens never reach the log', function (): void {
    $log = resolve(ActivityLogger::class)->record('oidc_client.updated', 'demo', details: [
        'name' => ['old' => 'a', 'new' => 'b'],
        'client_secret' => ['old' => 's1', 'new' => 's2'],
        'nested' => ['password' => 'hunter2'],
    ]);

    expect($log->fresh()->details)->toBe([
        'name' => ['old' => 'a', 'new' => 'b'],
        'client_secret' => '[redacted]',
        'nested' => ['password' => '[redacted]'],
    ]);
});

test('diff keeps only the fields that changed', function (): void {
    expect(ActivityLogger::diff(['name' => 'a', 'from' => '2026-01-01'], ['name' => 'b', 'from' => '2026-01-01', 'until' => '2026-12-31']))
        ->toBe([
            'name' => ['old' => 'a', 'new' => 'b'],
            'until' => ['old' => null, 'new' => '2026-12-31'],
        ]);
});

test('entries are never changed', function (): void {
    $log = resolve(ActivityLogger::class)->record('domain.created', 'demo');

    $log->update(['action' => 'domain.deleted']);
})->throws(LogicException::class);

test('entries older than the retention period are pruned', function (): void {
    config(['activity_log.retention_days' => 30]);
    $old = ActivityLog::forceCreate(['action' => 'domain.created', 'created_at' => Date::now()->subDays(31)]);
    $recent = ActivityLog::forceCreate(['action' => 'domain.created', 'created_at' => Date::now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertExitCode(0);

    expect(ActivityLog::find($old->id))->toBeNull()
        ->and(ActivityLog::find($recent->id))->not->toBeNull();
});

test('without a retention period nothing is pruned', function (): void {
    config(['activity_log.retention_days' => null]);
    $old = ActivityLog::forceCreate(['action' => 'domain.created', 'created_at' => Date::now()->subYears(10)]);

    $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertExitCode(0);

    expect(ActivityLog::find($old->id))->not->toBeNull();
});

test('relocating a DN repoints it and everything below it, nothing else', function (): void {
    $log = ActivityLog::forceCreate(['action' => 'role_membership.updated', 'details' => [
        'role' => ['old' => 'cn=mitglied,OU=fsr,ou=Committees,dc=example', 'new' => 'cn=vorsitz,ou=fsr,ou=Committees,dc=example'],
        'committee' => 'ou=fsr,ou=Committees,dc=example',
        'roles' => ['cn=mitglied,ou=fsr,ou=Committees,dc=example', 'cn=mitglied,ou=xfsr,ou=Committees,dc=example'],
        'comment' => 'ou=fsr,ou=Committees,dc=example is not a reference',
    ]]);
    $other = ActivityLog::forceCreate(['action' => 'domain.created', 'details' => ['name' => 'example.org']]);

    $updated = ActivityLog::relocateDn('ou=fsr,ou=Committees,dc=example', 'ou=fsr,ou=stura,ou=Committees,dc=example');

    expect($updated)->toBe(1)
        ->and($log->fresh()->details)->toBe([
            'role' => ['old' => 'cn=mitglied,ou=fsr,ou=stura,ou=Committees,dc=example', 'new' => 'cn=vorsitz,ou=fsr,ou=stura,ou=Committees,dc=example'],
            'committee' => 'ou=fsr,ou=stura,ou=Committees,dc=example',
            'roles' => ['cn=mitglied,ou=fsr,ou=stura,ou=Committees,dc=example', 'cn=mitglied,ou=xfsr,ou=Committees,dc=example'],
            'comment' => 'ou=fsr,ou=Committees,dc=example is not a reference',
        ])
        ->and($other->fresh()->details)->toBe(['name' => 'example.org']);
});

test('sign-ins can be kept for less time than other entries', function (): void {
    config(['activity_log.retention_days' => 365, 'activity_log.login_retention_days' => 30]);
    $login = ActivityLog::forceCreate(['action' => 'login.app', 'created_at' => Date::now()->subDays(31)]);
    $recentLogin = ActivityLog::forceCreate(['action' => 'login.oidc_client', 'created_at' => Date::now()->subDays(29)]);
    $change = ActivityLog::forceCreate(['action' => 'role.created', 'created_at' => Date::now()->subDays(31)]);

    $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertExitCode(0);

    expect(ActivityLog::find($login->id))->toBeNull()
        ->and(ActivityLog::find($recentLogin->id))->not->toBeNull()
        ->and(ActivityLog::find($change->id))->not->toBeNull();
});

test('only sign-ins are pruned when only their retention period is set', function (): void {
    config(['activity_log.retention_days' => null, 'activity_log.login_retention_days' => 30]);
    $login = ActivityLog::forceCreate(['action' => 'login.app', 'created_at' => Date::now()->subDays(31)]);
    $change = ActivityLog::forceCreate(['action' => 'role.created', 'created_at' => Date::now()->subYears(5)]);

    $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertExitCode(0);

    expect(ActivityLog::find($login->id))->toBeNull()
        ->and(ActivityLog::find($change->id))->not->toBeNull();
});
