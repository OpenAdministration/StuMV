<?php

use App\Livewire\Group\AddRoleToGroup;
use Livewire\Livewire;

// Skeleton: this Livewire component mounts with community-scoped route parameters
// (e.g. uid / ou / cn) and expects an authenticated LDAP member, which this stub
// does not yet provide. Flesh it out once a community-scoped test fixture exists.
test('renders successfully', function () {
    Livewire::test(AddRoleToGroup::class)->assertStatus(200);
})->skip('Needs community-scoped mount parameters and an authenticated LDAP member.');
