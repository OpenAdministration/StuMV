<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a member of a community visiting / is redirected straight to its dashboard, skipping the picker', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $this->get('/')->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));
});

test('a superadmin visiting / is redirected to the picker', function (): void {
    // Superadmin status is realm membership (the dedicated "admin" realm),
    // not a separate group - home() special-cases it to the picker
    // regardless of the "realm" column, since a superadmin is meant to
    // choose which OTHER realm to administer next.
    actingAsSuperAdmin();

    $this->get('/')->assertRedirect(route('realms.pick'));
});

test('RouteServiceProvider::home() falls back to the picker for a user with no LDAP account yet', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(RouteServiceProvider::home())->toBe(route('realms.pick'));
});
