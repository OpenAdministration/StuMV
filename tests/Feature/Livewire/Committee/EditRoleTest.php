<?php

use App\Ldap\Community;
use App\Livewire\Committee\EditRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the edit form for a seeded role', function () {
    actingAsModerator('demo');

    Livewire::test(EditRole::class, ['uid' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->assertStatus(200)
        ->assertSet('cn', 'mitglied');
});
