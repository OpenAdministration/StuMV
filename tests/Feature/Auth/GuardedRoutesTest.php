<?php

/**
 * Every application route sits behind the `auth` (and mostly `verified`)
 * middleware. A guest hitting any of them must be redirected to the login
 * screen rather than served the page or a 404.
 */
dataset('guarded routes', [
    'realm picker' => '/pick-realm',
    'new realm' => '/new-realm',
]);

test('guests are redirected to the generic login picker', function (string $route): void {
    $this->get($route)->assertRedirect('/login');
})->with('guarded routes');

/**
 * Realm-scoped routes ({realm}/...) are a special case: a guest hitting one
 * (e.g. because their session timed out mid-visit) is sent straight back to
 * that realm's own login page instead of the generic picker, so they don't
 * have to re-pick their community.
 */
dataset('realm-scoped guarded routes', [
    'profile' => '/testcom/profile/admin',
    'profile memberships' => '/testcom/profile/admin/memberships',
    'community dashboard' => '/testcom/dashboard',
    'community members' => '/testcom/members/',
    'committee tree' => '/testcom/committees',
]);

test('guests are redirected to their realm\'s own login page', function (string $route): void {
    $this->get($route)->assertRedirect('/testcom/login');
})->with('realm-scoped guarded routes');

test('guests hitting an unknown realm fall back to the generic login picker', function (): void {
    $this->get('/does-not-exist/dashboard')->assertRedirect('/login');
});

test('guest-only routes are reachable without authentication', function (string $route): void {
    $this->get($route)->assertStatus(200);
})->with([
    'login' => '/login',
    'forgot password' => '/testcom/forgot-password',
]);

test('the register picker redirects rather than 404s or requires auth', function (): void {
    $this->get('/register')->assertRedirect('/login');
});
