<?php

use App\Livewire\Tools\ToolsDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the uni LDAP tools are hidden from the dashboard when not configured', function (): void {
    config(['ldap.connections.uni.base_dn' => null]);
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ToolsDashboard::class, ['realm' => $community])
        ->assertDontSee(__('tools.importUsersFromUniLdap_headline'))
        ->assertDontSee(__('tools.usersNotInUniLdap_headline'))
        ->assertSee(__('tools.compareEmailList_headline'))
        ->assertSee(__('tools.unusedRoles_headline'));
});

test('the uni LDAP tools are shown on the dashboard when configured', function (): void {
    config(['ldap.connections.uni.base_dn' => 'ou=People,dc=stumv,dc=de']);
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ToolsDashboard::class, ['realm' => $community])
        ->assertSee(__('tools.importUsersFromUniLdap_headline'))
        ->assertSee(__('tools.usersNotInUniLdap_headline'));
});
