<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a matching entryUUID is left untouched', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);

    $this->artisan('app:sync-user-guids')->assertExitCode(0);

    expect($member->fresh()->uid)->toBe($member->uid);
});

test('a stale entryUUID is corrected to match the real LDAP entry', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $user = User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => $ldap->getFirstAttribute('uid'),
        'realm' => $community->getShortCode(),
    ]);

    $this->artisan('app:sync-user-guids')->assertExitCode(0);

    expect($user->fresh()->uid)->toBe($ldap->getConvertedGuid())
        ->not->toBe('stale-guid-value');
});

test('--dry-run reports the mismatch without writing it', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $user = User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => $ldap->getFirstAttribute('uid'),
        'realm' => $community->getShortCode(),
    ]);

    $this->artisan('app:sync-user-guids', ['--dry-run' => true])->assertExitCode(0);

    expect($user->fresh()->uid)->toBe('stale-guid-value');
});

test('scoped to one community leaves other communities\' stale rows untouched', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $otherLdap = TestLdap::makeUser(community: $otherCommunity);
    $user = User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => $ldap->getFirstAttribute('uid'),
        'realm' => $community->getShortCode(),
    ]);
    $otherUser = User::factory()->create([
        'uid' => 'other-stale-guid-value',
        'username' => $otherLdap->getFirstAttribute('uid'),
        'realm' => $otherCommunity->getShortCode(),
    ]);

    $this->artisan('app:sync-user-guids', ['community' => $community->getShortCode()])->assertExitCode(0);

    expect($user->fresh()->uid)->toBe($ldap->getConvertedGuid())
        ->and($otherUser->fresh()->uid)->toBe('other-stale-guid-value');
});

test('a user with no matching LDAP entry is reported but does not fail the command', function (): void {
    $community = newCommunity();
    $user = User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => 'ghost-'.uniqid(),
        'realm' => $community->getShortCode(),
    ]);

    $this->artisan('app:sync-user-guids')->assertExitCode(0);

    expect($user->fresh()->uid)->toBe('stale-guid-value');
});

test('a user whose realm no longer exists is skipped, not fatal', function (): void {
    $user = User::factory()->create([
        'uid' => 'stale-guid-value',
        'username' => 'orphan-'.uniqid(),
        'realm' => 'no-such-realm-'.uniqid(),
    ]);

    $this->artisan('app:sync-user-guids')->assertExitCode(0);

    expect($user->fresh()->uid)->toBe('stale-guid-value');
});
