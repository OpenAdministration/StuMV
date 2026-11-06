<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full HTTP requests (not Livewire::test()) so the wrapping layout,
 * breadcrumbs and navigation actually compile - Livewire::test() renders
 * the component in isolation and would miss a missing breadcrumb
 * registration or an unknown Flux icon name in the layout around it.
 */
uses(RefreshDatabase::class);

test('the api clients list page renders over HTTP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $admin = actingAsAdmin($community);

    $this->actingAs($admin)->get("/$uid/api-clients")->assertStatus(200);
});

test('the new api client page renders over HTTP', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $admin = actingAsAdmin($community);

    $this->actingAs($admin)->get("/$uid/new-api-client")->assertStatus(200);
});
