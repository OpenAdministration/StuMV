<?php

use App\Ldap\Community;
use App\Livewire\Group\AddRoleToGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders the add-role-to-group screen for an admin', function (): void {
    actingAsAdmin('demo');

    Livewire::test(AddRoleToGroup::class, ['uid' => Community::findByUid('demo'), 'cn' => 'some-group'])
        ->assertStatus(200)
        ->assertSet('group_cn', 'some-group');
});
