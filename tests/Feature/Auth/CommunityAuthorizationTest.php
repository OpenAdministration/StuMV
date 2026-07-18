<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Drives the community authorization stack end-to-end over real HTTP routes:
 * the communityMember / communityAdmin / SuperAdmin middleware and the policies
 * behind them, all resolving against LDAP group membership. The actingAs*
 * helpers (tests/Pest.php) build self-cleaning LDAP-backed users at each level.
 */
uses(RefreshDatabase::class);

test('a community member can open the dashboard', function (): void {
    $member = actingAsMember('demo');

    $this->actingAs($member)->get('/demo/dashboard')->assertStatus(200);
});

test('a plain member is forbidden from admin and superadmin routes', function (): void {
    $member = actingAsMember('demo');

    $this->actingAs($member)->get('/demo/edit')->assertStatus(403);       // communityAdmin
    $this->actingAs($member)->get('/demo/api-clients')->assertStatus(403); // communityAdmin
    $this->actingAs($member)->get('/new-realm')->assertStatus(403);        // SuperAdmin
});

test('a moderator can open a moderator-only route', function (): void {
    $moderator = actingAsModerator('demo');

    $this->actingAs($moderator)->get('/demo/new-committee')->assertStatus(200);
});

test('a plain member is forbidden from moderator-only routes', function (): void {
    $member = actingAsMember('demo');

    $this->actingAs($member)->get('/demo/new-committee')->assertStatus(403);
});

test('a community admin can open the community edit screen', function (): void {
    $admin = actingAsAdmin('demo');

    $this->actingAs($admin)->get('/demo/edit')->assertStatus(200);
    $this->actingAs($admin)->get('/demo/api-clients')->assertStatus(200);
});

test('a super admin can open the new-realm screen', function (): void {
    $superAdmin = actingAsSuperAdmin();

    $this->actingAs($superAdmin)->get('/new-realm')->assertStatus(200);
});

test('a member of another community cannot enter this one', function (): void {
    $outsider = actingAsMember('testcom');

    $this->actingAs($outsider)->get('/demo/dashboard')->assertStatus(403);
});
