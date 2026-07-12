<?php

use App\Ldap\Community;
use App\Livewire\Committee\ListRoleMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the member list for a seeded role', function (): void {
    actingAsModerator('demo');

    Livewire::test(ListRoleMembers::class, ['uid' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->assertStatus(200)
        ->assertSet('cn', 'mitglied');
});

test('the member list can be lazily loaded', function (): void {
    actingAsModerator('demo');

    Livewire::test(ListRoleMembers::class, ['uid' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->assertSet('ready', false)
        ->call('loadMembers')
        ->assertSet('ready', true);
});
