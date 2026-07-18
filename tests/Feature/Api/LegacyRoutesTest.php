<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the old delegated-user API now lives under /api-legacy, not /api', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);

    Passport::actingAs($user, ['committees']);

    $this->getJson('/api/user')->assertNotFound();
    $this->getJson('/api-legacy/user')->assertOk();
    $this->getJson('/api-legacy/my/committees')->assertOk();
});
