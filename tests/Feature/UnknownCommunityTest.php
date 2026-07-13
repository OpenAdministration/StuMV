<?php

/**
 * The global `Route::bind('uid', ...)` (RouteServiceProvider) resolves the
 * "uid" route parameter via LdapRecord's findByOrFail(), which throws a raw
 * LdapRecord\Query\ObjectNotFoundException on no match. Without the mapping
 * in App\Exceptions\Handler, that leaked as an uncaught 500 instead of a
 * clean 404 - reproduced by requesting any "{uid}/..." route (web or API)
 * with a community short name that doesn't exist.
 */
test('an unknown community in a web route renders a 404, not a 500', function (): void {
    actingAsSuperAdmin();

    $this->get('/does-not-exist/dashboard')->assertNotFound();
});

test('an unknown community in an API route renders a 404, not a 500', function (): void {
    $this->getJson('/api/does-not-exist/committees')->assertNotFound();
});
