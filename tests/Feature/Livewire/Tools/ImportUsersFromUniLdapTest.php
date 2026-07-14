<?php

use App\Livewire\Tools\ImportUsersFromUniLdap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Connection;
use LdapRecord\Container;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The component connects via LdapRecord's pre-configured "uni" connection
 * (config/ldap.php), and gates the feature on that connection's base_dn
 * being configured - there's no per-realm database row anymore.
 */
beforeEach(function (): void {
    config(['ldap.connections.uni.base_dn' => 'ou=People,dc=stumv,dc=de']);

    Container::addConnection(new Connection([
        'hosts' => ['127.0.0.1'],
        'port' => 13389,
        'base_dn' => 'ou=People,dc=stumv,dc=de',
    ]), 'uni');
});

test('the page 404s when the uni LDAP connection has no base_dn configured', function (): void {
    config(['ldap.connections.uni.base_dn' => null]);
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['realm' => $community])
        ->assertStatus(404);
});

test('the feature is shown when the uni LDAP connection has a base_dn configured', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['realm' => $community])
        ->assertStatus(200);
});

test('searching for an email with no match in the university LDAP reports not found', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['realm' => $community])
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
    actingAsAdmin($community);

    Livewire::test(ImportUsersFromUniLdap::class, ['realm' => $community])
        ->set('email', 'someone@example.test')
        ->call('getUserData')
        ->assertSet('searchCompleted', true)
        ->assertSet('userNotFound', true);
});
