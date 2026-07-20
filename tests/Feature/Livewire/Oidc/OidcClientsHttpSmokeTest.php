<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full HTTP requests (not Livewire::test()) so the wrapping layout,
 * breadcrumbs and navigation actually compile - Livewire::test() renders
 * the component in isolation and would miss a missing breadcrumb
 * registration or an unknown Flux icon name in the layout around it. See
 * Api\ApiClientsHttpSmokeTest for the equivalent Directory API coverage.
 */
uses(RefreshDatabase::class);

test('the oidc clients list page renders over HTTP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $admin = actingAsAdmin($community);

    $this->actingAs($admin)->get("/$uid/oidc-clients")->assertStatus(200);
});

test('the new oidc client page renders over HTTP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $admin = actingAsAdmin($community);

    $this->actingAs($admin)->get("/$uid/new-oidc-client")->assertStatus(200);
});

test('the community dashboard (with its oidc-clients card) renders over HTTP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $admin = actingAsAdmin($community);

    $this->actingAs($admin)->get("/$uid/dashboard")->assertStatus(200);
});

test('the admin realm dashboard renders over HTTP without the oidc-clients card', function (): void {
    $superAdmin = actingAsSuperAdmin();

    $this->actingAs($superAdmin)->get('/admin/dashboard')->assertStatus(200);
});
