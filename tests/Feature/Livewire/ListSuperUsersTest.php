<?php

use App\Livewire\ListSuperUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the list links each super admin to their profile', function (): void {
    $superadmin = actingAsSuperAdmin();

    Livewire::test(ListSuperUsers::class)
        ->assertSeeHtml(route('profile', ['username' => $superadmin->username]));
});
