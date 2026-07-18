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
        ->assertDontSee(__('tools.import_users_from_uni_ldap_headline'))
        ->assertDontSee(__('tools.users_not_in_uni_ldap_headline'))
        ->assertSee(__('tools.compare_email_list_headline'))
        ->assertSee(__('tools.unused_roles_headline'));
});

test('the uni LDAP tools are shown on the dashboard when configured', function (): void {
    config(['ldap.connections.uni.base_dn' => 'ou=People,dc=stumv,dc=de']);
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(ToolsDashboard::class, ['realm' => $community])
        ->assertSee(__('tools.import_users_from_uni_ldap_headline'))
        ->assertSee(__('tools.users_not_in_uni_ldap_headline'));
});
