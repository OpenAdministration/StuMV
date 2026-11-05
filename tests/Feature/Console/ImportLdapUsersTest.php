<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('an LDAP entry without a database row is imported', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode()])->assertExitCode(0);

    $user = User::where('uid', $ldap->getConvertedGuid())->firstOrFail();
    expect($user->username)->toBe($ldap->getFirstAttribute('uid'))
        ->and($user->realm)->toBe($community->getShortCode())
        ->and($user->full_name)->toBe($ldap->getFirstAttribute('cn'))
        ->and($user->email)->toBe($ldap->getFirstAttribute('mail'))
        ->and($user->email_verified_at)->toBeNull();
});

test('--verified marks the imported email as verified', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode(), '--verified' => true])->assertExitCode(0);

    expect(User::where('uid', $ldap->getConvertedGuid())->firstOrFail()->email_verified_at)->not->toBeNull();
});

test('an entry that already has a database row is left alone', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode()])->assertExitCode(0);

    expect(User::where('username', $member->username)->where('realm', $community->getShortCode())->count())->toBe(1);
});

test('a row with the same username but a stale entryUUID is not duplicated', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => $ldap->getFirstAttribute('uid'),
        'realm' => $community->getShortCode(),
    ]);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode()])
        ->expectsOutputToContain('run app:sync-user-guids')
        ->assertExitCode(0);

    expect(User::where('username', $ldap->getFirstAttribute('uid'))->count())->toBe(1);
});

test('--dry-run reports the missing row without writing it', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode(), '--dry-run' => true])
        ->expectsOutputToContain($community->getShortCode().'/'.$ldap->getFirstAttribute('uid'))
        ->assertExitCode(0);

    expect(User::where('uid', $ldap->getConvertedGuid())->exists())->toBeFalse();
});

test('scoped to one community leaves other communities untouched', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    TestLdap::makeUser(community: $community);
    $otherLdap = TestLdap::makeUser(community: $otherCommunity);

    $this->artisan('app:import-ldap-users', ['community' => $community->getShortCode()])->assertExitCode(0);

    expect(User::where('uid', $otherLdap->getConvertedGuid())->exists())->toBeFalse();
});

test('an unknown realm fails', function (): void {
    $this->artisan('app:import-ldap-users', ['community' => 'does-not-exist'])->assertExitCode(1);
});
