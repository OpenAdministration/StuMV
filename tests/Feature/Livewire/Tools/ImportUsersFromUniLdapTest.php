<?php

use App\Livewire\Tools\ImportUsersFromUniLdap;
use App\Models\UniLdap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Connection;
use LdapRecord\Container;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The component now connects via LdapRecord's pre-configured "uni" connection
 * (config/ldap.php) rather than building one from the UniLdap database row's
 * host/members_base columns - those remain only as a per-realm on/off toggle
 * for the feature (see mount()'s unildapDataExists check).
 */
beforeEach(function (): void {
    Container::addConnection(new Connection([
        'hosts' => ['127.0.0.1'],
        'port' => 13389,
        'base_dn' => 'ou=People,dc=stumv,dc=de',
    ]), 'uni');
});

test('searching for an email with no match in the university LDAP reports not found', function (): void {
    $community = newCommunity();
    UniLdap::create([
        'realm' => $community->getShortCode(),
        'host' => 'unused',
        'members_base' => 'unused',
    ]);
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['uid' => $community])
        ->set('email', 'nobody-such-address@example.test')
        ->call('getUserData')
        ->assertSet('searchCompleted', true)
        ->assertSet('userNotFound', true);
});

test('an unreachable university LDAP connection is handled gracefully instead of crashing', function (): void {
    Container::addConnection(new Connection([
        'hosts' => ['127.0.0.1'],
        'port' => 1,
        'base_dn' => 'ou=People,dc=stumv,dc=de',
    ]), 'uni');

    $community = newCommunity();
    UniLdap::create([
        'realm' => $community->getShortCode(),
        'host' => 'unused',
        'members_base' => 'unused',
    ]);
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['uid' => $community])
        ->set('email', 'someone@example.test')
        ->call('getUserData')
        ->assertSet('searchCompleted', true)
        ->assertSet('userNotFound', true);
});
