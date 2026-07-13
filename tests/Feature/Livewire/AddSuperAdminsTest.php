<?php

use App\Livewire\AddSuperAdmins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the select excludes users who are already super admins', function (): void {
    $existingSuperAdmin = TestLdap::superAdmin();
    $eligibleUser = TestLdap::makeUser();
    actingAsSuperAdmin();

    $usernames = Livewire::test(AddSuperAdmins::class)
        ->viewData('users')
        ->map(fn ($user) => $user->getFirstAttribute('uid'));

    expect($usernames)->toContain($eligibleUser->getFirstAttribute('uid'))
        ->not->toContain($existingSuperAdmin->username);
});
