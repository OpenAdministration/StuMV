<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a guest hitting a 404 sees the guest-layout error page with a login link', function (): void {
    $response = $this->get('/this-route-does-not-exist-anywhere');

    $response->assertNotFound()
        ->assertSee(__('errors.404_title'))
        ->assertSee(__('errors.back_to_login'))
        ->assertDontSee(__('errors.back_to_dashboard'));
});

test('a logged-in user hitting a 404 for a missing record within their own realm sees the app-layout error page with a dashboard link', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    // {realm} resolves fine here (it's the group cn that's missing), so
    // unlike an invalid realm slug, Route::current()->parameter('realm')
    // is already a real Community by the time this 404 renders.
    $response = $this->get(route('realms.groups.edit', ['realm' => $community->getShortCode(), 'cn' => 'this-group-does-not-exist']));

    $response->assertNotFound()
        ->assertSee(__('errors.404_title'))
        ->assertSee(__('errors.back_to_dashboard'))
        ->assertDontSee(__('errors.back_to_login'));
});

test('a logged-in user hitting a 404 for a url matching no route at all still sees the app-layout error page', function (): void {
    $community = newCommunity();

    // Deliberately NOT actingAsMember(): that helper sets the auth guard's
    // user directly and stays in effect for every request the test makes
    // regardless of middleware, which is exactly what let this bug (#reported
    // by the user - guest layout shown despite being logged in) slip past
    // the first version of this test. A URL matching no route at all never
    // enters the "web" middleware group (see routes/web.php's
    // Route::fallback() comment) - only a real login, whose session cookie
    // survives via the fallback route's own "web" group middleware, proves
    // auth()->check() is still true by the time the 404 renders.
    $username = 'realsession'.bin2hex(random_bytes(4));
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $ldapUser = TestLdap::makeUser($username, $community);
    $ldapUser->userPassword = '{ARGON2}'.password_hash($password, PASSWORD_ARGON2ID);
    $ldapUser->save();
    TestLdap::databaseUser($ldapUser, $community);

    $this->post(route('realm.login', ['realm' => $community->getShortCode()]), [
        'uid' => $username,
        'password' => $password,
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticated();

    // A typo in the path segment itself (not the realm) - "memberss" isn't
    // "members", so this matches no route at all (Route::current() would be
    // null without the Route::fallback() in routes/web.php).
    $response = $this->get('/'.$community->getShortCode().'/memberss');

    $response->assertNotFound()
        ->assertSee(__('errors.404_title'))
        ->assertSee(__('errors.back_to_dashboard'))
        ->assertDontSee(__('errors.back_to_login'))
        // The realm-scoped 404 (routes/web.php's realms.fallback) is a real,
        // named, realm-bound route - unlike a completely unmatched URL, it
        // gets the same sidebar navigation and breadcrumb trail any other
        // {realm}/... page would, plus a "404" crumb (routes/breadcrumbs.php).
        ->assertSeeHtml(route('realms.dashboard', ['realm' => $community->getShortCode()]))
        ->assertSeeText('404');
});

test('a logged-in user hitting a 404 for an invalid realm slug falls back to the guest-layout page', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    // The realm binding itself is what fails here, so Route::current()->
    // parameter('realm') is still the raw route segment string, not a
    // Community - components/navigation.blade.php and header.blade.php
    // both call Community-only methods on it unconditionally, so the rich
    // layout is deliberately skipped even though the visitor is logged in.
    $response = $this->get('/this-realm-does-not-exist/dashboard');

    $response->assertNotFound()
        ->assertSee(__('errors.404_title'))
        ->assertSee(__('errors.back_to_login'))
        ->assertDontSee(__('errors.back_to_dashboard'));
});

test('a guest hitting a 403 sees the guest-layout error page', function (): void {
    $community = newCommunity();

    $response = $this->get(route('realms.identity-providers.new', ['realm' => $community->getShortCode()]));

    $response->assertRedirect();
});

test('a logged-in non-admin hitting a 403 sees the app-layout error page with a dashboard link', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->get(route('realms.identity-providers', ['realm' => $community->getShortCode()]));

    $response->assertForbidden()
        ->assertSee(__('errors.403_title'))
        ->assertSee(__('errors.back_to_dashboard'))
        // Appended generically by components/header.blade.php for every
        // app-layout error page, on top of whatever breadcrumb trail
        // realms.identity-providers itself already has registered.
        ->assertSeeText('403');
});

test('a guest hitting a 500 sees the guest-layout error page', function (): void {
    config(['app.debug' => false]);
    Route::get('/__error_pages_test_500', function (): void {
        abort(500, 'deliberate test failure');
    })->middleware('web');

    $response = $this->get('/__error_pages_test_500');

    $response->assertStatus(500)
        ->assertSee(__('errors.500_title'))
        ->assertSee(__('errors.back_to_login'))
        ->assertDontSee(__('errors.back_to_dashboard'));
});

test('a logged-in user hitting a 500 within their own realm sees the app-layout error page with a dashboard link', function (): void {
    config(['app.debug' => false]);
    $community = newCommunity();
    actingAsMember($community);

    // Named, with a matching breadcrumb - unlike the guest-500 test's route
    // above: components/header.blade.php calls Breadcrumbs::render(Route::
    // current()->getName(), ...), which requires both for whatever route it
    // renders for. Every real route in this app already satisfies that
    // (see routes/breadcrumbs.php); this ad-hoc test route needs to fake it.
    //
    // POST, not GET: routes/web.php's realms.fallback ({realm}/{any}, GET
    // only) would otherwise shadow this route entirely - it's registered
    // during normal app bootstrap, long before this test route exists, and
    // Laravel matches routes in registration order.
    Route::post('{realm}/__error_pages_test_500', function (): void {
        abort(500, 'deliberate test failure');
    })->middleware('web')->name('test.error500realm');
    Breadcrumbs::for('test.error500realm', fn ($trail) => $trail->push('Test'));

    $response = $this->post('/'.$community->getShortCode().'/__error_pages_test_500');

    $response->assertStatus(500)
        ->assertSee(__('errors.500_title'))
        ->assertSee(__('errors.back_to_dashboard'))
        ->assertDontSee(__('errors.back_to_login'))
        ->assertSeeText('500');
});
