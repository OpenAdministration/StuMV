<?php

use App\Models\User;
use Tests\Support\TestLdap;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full Laravel application (via Tests\TestCase) so they
| can hit routes, Livewire components, the database and the dockerised LDAP.
| Unit tests stay on the plain PHPUnit test case: they exercise pure logic
| (model configuration, value objects) without booting the framework.
|
| After every feature test we purge any throwaway LDAP users/memberships that
| the actingAs* helpers below created, so runs never leak directory state.
|
*/

pest()->extend(TestCase::class)
    ->afterEach(fn () => TestLdap::cleanup())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Authenticated user helpers
|--------------------------------------------------------------------------
|
| Each helper creates a real, self-cleaning LDAP-backed user at the requested
| authorization level, logs it in, and returns the App\Models\User so it can be
| reused (e.g. `$this->actingAs(actingAsAdmin())->get(...)`). "member",
| "moderator" and "admin" are scoped to a community (default: the seeded "demo"
| community); "superAdmin" is global. See Tests\Support\TestLdap.
|
*/

function actingAsMember(string $community = 'demo'): User
{
    return tap(TestLdap::member($community), fn (User $user) => test()->actingAs($user));
}

function actingAsModerator(string $community = 'demo'): User
{
    return tap(TestLdap::moderator($community), fn (User $user) => test()->actingAs($user));
}

function actingAsAdmin(string $community = 'demo'): User
{
    return tap(TestLdap::admin($community), fn (User $user) => test()->actingAs($user));
}

function actingAsSuperAdmin(): User
{
    return tap(TestLdap::superAdmin(), fn (User $user) => test()->actingAs($user));
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidUuid', fn () => $this->toMatch('/^[0-9a-f-]{36}$/i'));
