<?php

/**
 * Every application route sits behind the `auth` (and mostly `verified`)
 * middleware. A guest hitting any of them must be redirected to the login
 * screen rather than served the page or a 404.
 */
dataset('guarded routes', [
    'realm picker' => '/pick-realm',
    'profile' => '/testcom/profile/admin',
    'profile memberships' => '/testcom/profile/admin/memberships',
    'community dashboard' => '/testcom/dashboard',
    'community members' => '/testcom/members/',
    'committee tree' => '/testcom/committees',
    'new realm' => '/new-realm',
]);

test('guests are redirected to login', function (string $route): void {
    $this->get($route)->assertRedirect('/login');
})->with('guarded routes');

test('guest-only routes are reachable without authentication', function (string $route): void {
    $this->get($route)->assertStatus(200);
})->with([
    'login' => '/login',
    'forgot password' => '/testcom/forgot-password',
]);

test('the register picker redirects rather than 404s or requires auth', function (): void {
    $this->get('/register')->assertRedirect('/login');
});
