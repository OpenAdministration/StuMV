<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('group pages 404 for a group that does not exist', function (string $route): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route($route, ['realm' => $community->getShortCode(), 'cn' => 'does-not-exist']))->assertNotFound();
})->with([
    'roles' => 'realms.groups.roles',
    'add role' => 'realms.groups.roles.add',
    'members' => 'realms.groups.members',
    'edit' => 'realms.groups.edit',
]);
