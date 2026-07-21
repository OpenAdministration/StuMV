<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression test for a bug where every translated string across the app
 * (dashboards, profile pages, the sidebar nav, the profile dropdown - all
 * reported independently, but all one root cause) silently stayed in the
 * config-default locale regardless of the browser's Accept-Language header.
 *
 * AppServiceProvider::boot() resolves the translator singleton earlier in
 * the boot cycle than routes/web.php's language-detection code runs (inside
 * a routes() callback registered by RouteServiceProvider) - the translator's
 * locale gets snapshotted from config('app.locale') at that earlier point,
 * so merely calling Config::set('app.locale', ...) afterwards (as opposed to
 * App::setLocale(...), which explicitly re-propagates to an already-resolved
 * translator) never actually changes what __() resolves to for the rest of
 * the request. This only shows up with a real Accept-Language header -
 * calling app()->setLocale() directly in a test bypasses the exact broken
 * mechanism and would not have caught it.
 */
test('a browser preferring English sees English text, not the German config default', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->withHeaders(['Accept-Language' => 'en'])
        ->get('/'.$community->getShortCode().'/dashboard');

    $response->assertOk();
    expect(app()->getLocale())->toBe('en');
    expect(resolve('translator')->getLocale())->toBe('en');
    $response->assertSee(__('realms.nav_dashboard'))
        ->assertDontSee('Übersicht');
});

test('a browser preferring German still sees German text', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->withHeaders(['Accept-Language' => 'de'])
        ->get('/'.$community->getShortCode().'/dashboard');

    $response->assertOk();
    expect(app()->getLocale())->toBe('de');
    $response->assertSee('Übersicht');
});
