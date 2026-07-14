<?php

/**
 * Every application route sits behind the `auth` (and mostly `verified`)
 * middleware. A guest hitting any of them must be redirected to the login
 * screen rather than served the page or a 404.
 */
dataset('guarded routes', [
    'realm picker' => '/pick-realm',
    'profile' => '/profile/admin',
    'profile memberships' => '/profile/admin/memberships',
    'community dashboard' => '/testcom/dashboard',
    'community members' => '/testcom/members/',
    'committee tree' => '/testcom/committees',
    'superadmin list' => '/superadmins',
    'new realm' => '/new-realm',
]);

test('guests are redirected to login', function (string $route): void {
    $this->get($route)->assertRedirect('/login');
})->with('guarded routes');

test('guest-only routes are reachable without authentication', function (string $route): void {
    $this->get($route)->assertStatus(200);
})->with([
    'login' => '/login',
    'register' => '/register',
    'forgot password' => '/forgot-password',
]);
