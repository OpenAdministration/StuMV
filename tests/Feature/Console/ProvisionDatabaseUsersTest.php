<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a dry run reports LDAP accounts without user row and changes nothing', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);
    TestLdap::member($community); // has its row already

    $this->artisan('app:provision-database-users', ['community' => $community->getShortCode(), '--dry-run' => true])
        ->expectsOutputToContain('Would fix 1 missing and 0 mislinked user row(s).')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('user', ['username' => $ldapUser->getFirstAttribute('uid')]);
});

test('missing user rows are created and linked', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);

    $this->artisan('app:provision-database-users', ['community' => $community->getShortCode()])
        ->expectsOutputToContain('Fixed 1 missing and 0 mislinked user row(s).')
        ->assertExitCode(0);

    $this->assertDatabaseHas('user', [
        'username' => $ldapUser->getFirstAttribute('uid'),
        'realm' => $community->getShortCode(),
        'uid' => $ldapUser->getConvertedGuid(),
    ]);
});

test('an unknown realm fails', function (): void {
    $this->artisan('app:provision-database-users', ['community' => 'does-not-exist'])
        ->assertExitCode(1);
});
