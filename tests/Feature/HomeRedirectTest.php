<?php

use App\Ldap\SuperUserGroup;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a member of a single community visiting / is redirected straight to its dashboard, skipping the picker', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $this->get('/')->assertRedirect(route('realms.dashboard', ['realm' => $community->getShortCode()]));
});

test('a member of several communities visiting / is redirected to the picker', function (): void {
    $communityA = newCommunity();
    $communityB = newCommunity();
    $ldapUser = TestLdap::makeUser();
    TestLdap::attach($communityA->membersGroup(), $ldapUser);
    TestLdap::attach($communityB->membersGroup(), $ldapUser);
    $this->actingAs(TestLdap::databaseUser($ldapUser));

    $this->get('/')->assertRedirect(route('realms.pick'));
});

test('a superadmin visiting / is redirected to the picker even with a single membership', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser();
    TestLdap::attach(SuperUserGroup::group(), $ldapUser);
    TestLdap::attach($community->membersGroup(), $ldapUser);
    $this->actingAs(TestLdap::databaseUser($ldapUser));

    $this->get('/')->assertRedirect(route('realms.pick'));
});

test('RouteServiceProvider::home() falls back to the picker for a user with no LDAP account yet', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(RouteServiceProvider::home())->toBe(route('realms.pick'));
});
