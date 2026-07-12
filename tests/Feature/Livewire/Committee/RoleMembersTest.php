<?php

use App\Ldap\Community;
use App\Livewire\Committee\ListRoleMembers;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('cancelling the delete modal closes it', function (): void {
    $moderator = actingAsModerator('demo');

    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => 'ou=FSR,ou=Committees,ou=demo,ou=Communities,dc=stumv,dc=de',
        'username' => $moderator->username,
        'from' => today(),
    ]);

    Livewire::test(ListRoleMembers::class, ['uid' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->call('prepareDeletion', $membership->id)
        ->call('close')
        ->assertDispatched('modal-close', name: 'delete');
});

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
