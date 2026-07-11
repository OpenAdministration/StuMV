<?php

use App\Ldap\Community;
use App\Livewire\Committee\EditCommittee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the edit form for a seeded committee', function (): void {
    actingAsModerator('demo');

    Livewire::test(EditCommittee::class, ['uid' => Community::findByUid('demo'), 'ou' => 'FSR'])
        ->assertStatus(200)
        ->assertSet('ou', 'FSR');
});
