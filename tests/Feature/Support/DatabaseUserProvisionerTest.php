<?php

use App\Models\User;
use App\Support\DatabaseUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('an account that never signed in gets a user row linked to its LDAP entry', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);

    $row = DatabaseUserProvisioner::ensure($community, $ldapUser);

    expect($row->exists)->toBeTrue()
        ->and($row->username)->toBe($ldapUser->getFirstAttribute('uid'))
        ->and($row->realm)->toBe($community->getShortCode())
        ->and($row->uid)->toBe($ldapUser->getConvertedGuid())
        ->and($row->email)->toBe($ldapUser->getFirstAttribute('mail'))
        ->and($row->email_verified_at)->toBeNull();
});

test('a row signed in before realms were stored is adopted instead of duplicated', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);
    $unscoped = User::factory()->create([
        'username' => $ldapUser->getFirstAttribute('uid'),
        'uid' => $ldapUser->getConvertedGuid(),
        'realm' => null,
    ]);

    $row = DatabaseUserProvisioner::ensure($community, $ldapUser);

    expect($row->id)->toBe($unscoped->id)
        ->and($row->realm)->toBe($community->getShortCode())
        ->and(User::where('username', $row->username)->count())->toBe(1);
});

test('an existing row of the realm is relinked to the entry that now lives there', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);
    $row = User::factory()->create([
        'username' => $ldapUser->getFirstAttribute('uid'),
        'uid' => 'stale-guid',
        'realm' => $community->getShortCode(),
    ]);

    expect(DatabaseUserProvisioner::ensure($community, $ldapUser)->id)->toBe($row->id)
        ->and($row->fresh()->uid)->toBe($ldapUser->getConvertedGuid());
});

test('another realm row still holding the GUID releases it', function (): void {
    $realmA = newCommunity();
    $realmB = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $realmB);
    // before the split the original entry was linked to realm A's row
    $rowA = User::factory()->create([
        'username' => $ldapUser->getFirstAttribute('uid'),
        'uid' => $ldapUser->getConvertedGuid(),
        'realm' => $realmA->getShortCode(),
    ]);

    $rowB = DatabaseUserProvisioner::ensure($realmB, $ldapUser);

    expect($rowB->id)->not->toBe($rowA->id)
        ->and($rowB->uid)->toBe($ldapUser->getConvertedGuid())
        ->and($rowA->fresh()->uid)->toBeNull();
});

test('a clone of an account with a verified address stays verified', function (): void {
    $realmA = newCommunity();
    $realmB = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $realmB);
    User::factory()->create([
        'username' => $ldapUser->getFirstAttribute('uid'),
        'email' => $ldapUser->getFirstAttribute('mail'),
        'email_verified_at' => now()->subYear(),
        'realm' => $realmA->getShortCode(),
    ]);

    expect(DatabaseUserProvisioner::ensure($realmB, $ldapUser)->email_verified_at)->not->toBeNull();
});
