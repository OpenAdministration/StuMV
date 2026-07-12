<?php

use App\Livewire\SyncLdap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a success toast is shown after syncing LDAP', function (): void {
    actingAsSuperAdmin();

    Livewire::test(SyncLdap::class)
        ->call('syncLdap')
        ->assertDispatched('toast-show');
});
