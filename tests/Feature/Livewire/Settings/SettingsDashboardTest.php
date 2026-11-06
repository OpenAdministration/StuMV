<?php

use App\Ldap\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin sees all settings tiles', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    $this->get(route('settings.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->assertSee(route('realms.edit', $uid))
        ->assertSee(route('realms.branding', $uid))
        ->assertSee(route('realms.domains', $uid))
        ->assertSee(route('realms.api-clients', $uid))
        ->assertSee(route('realms.oidc-clients', $uid))
        ->assertSee(route('realms.identity-providers', $uid));
});

test('the admin realm only offers editing and branding', function (): void {
    actingAsSuperAdmin();
    $uid = Community::ADMIN_REALM_UID;

    $this->get(route('settings.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->assertSee(route('realms.edit', $uid))
        ->assertSee(route('realms.branding', $uid))
        ->assertDontSee(route('realms.domains', $uid))
        ->assertDontSee(route('realms.api-clients', $uid));
});

test('a moderator cannot open the settings', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('settings.dashboard', ['realm' => $community->getShortCode()]))
        ->assertForbidden();
});

test('the settings pages sit below the settings breadcrumb', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    $this->get(route('realms.domains', ['realm' => $uid]))
        ->assertOk()
        ->assertSeeInOrder([route('settings.dashboard', $uid), __('realms.domains_breadcrumb')]);
});
