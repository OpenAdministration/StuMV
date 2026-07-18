<?php

use App\Ldap\Community;
use App\Livewire\Realm\CommunityDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the dashboard for a community member', function (): void {
    actingAsMember('demo');

    Livewire::test(CommunityDashboard::class, ['realm' => Community::findByUid('demo')])
        ->assertStatus(200);
});
