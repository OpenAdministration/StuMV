<?php

use App\Livewire\Realm\ListRealms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * The realm picker (pick-realm) decides where a user lands: super admins get the
 * full list, while a normal user is routed by their LDAP `memberOf` groups — and
 * if they belong to exactly one community, sent straight to its dashboard.
 */
uses(RefreshDatabase::class);

test('a super admin sees the community picker', function (): void {
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)->assertStatus(200);
});

test('a member of a single community is sent straight to its dashboard', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    Livewire::test(ListRealms::class)
        ->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));
});
