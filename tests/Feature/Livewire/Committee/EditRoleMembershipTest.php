<?php

use App\Ldap\Community;
use App\Livewire\Committee\EditRoleMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the edit form for an existing membership', function (): void {
    $moderator = actingAsModerator('demo');

    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => 'ou=FSR,ou=Committees,ou=demo,ou=Communities,dc=stumv,dc=de',
        // FK: role_user_relation.username references user.username.
        'username' => $moderator->username,
        'from' => today(),
    ]);

    Livewire::test(EditRoleMembership::class, [
        'realm' => Community::findByUid('demo'),
        'ou' => 'FSR',
        'cn' => 'mitglied',
        'id' => $membership->id,
    ])
        ->assertStatus(200)
        ->assertSet('username', $moderator->username);
});
