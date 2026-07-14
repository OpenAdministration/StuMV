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

test('saving with a non-existent user dn reports an error instead of crashing', function (): void {
    actingAsSuperAdmin();

    Livewire::test(AddSuperAdmins::class)
        ->set('usersToAdd', ['uid=does-not-exist,ou=Users,dc=stumv,dc=de'])
        ->call('save')
        ->assertHasErrors('dn');
});
