<?php

use App\Models\RealmBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('responses carry a same-origin content security policy header', function (): void {
    $response = $this->get(route('login'));

    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toMatch('/script-src[^;]*\'nonce-[^\']+\'/')
        ->toMatch('/style-src[^;]*\'nonce-[^\']+\'/')
        ->not->toContain('unsafe-eval')
        ->not->toContain('unsafe-inline');
});

test('a realm\'s branding background image is applied via a nonced style block, not an inline style attribute', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create(['realm' => $uid, 'background_id' => 'test-bg.webp']);

    $response = $this->get(route('realm.login', ['realm' => $uid]));

    $response->assertOk();
    $csp = $response->headers->get('Content-Security-Policy');
    preg_match('/style-src[^;]*\'nonce-([^\']+)\'/', (string) $csp, $match);
    $nonce = $match[1] ?? null;

    expect($nonce)->not->toBeNull()
        ->and($response->getContent())
        ->toContain("<style nonce=\"{$nonce}\">")
        ->toContain("background-image: url('".asset('storage/realm-branding/test-bg.webp')."')")
        ->not->toContain('style="background-image');
});

test('a 404 for a url matching no route at all still carries a matching nonced content security policy', function (): void {
    $response = $this->get('/this-route-does-not-exist-anywhere');

    $response->assertNotFound();

    $csp = $response->headers->get('Content-Security-Policy');
    preg_match('/style-src[^;]*\'nonce-([^\']+)\'/', (string) $csp, $match);
    $nonce = $match[1] ?? null;

    expect($csp)->not->toBeNull()
        ->and($nonce)->not->toBeNull()
        ->and($response->getContent())->toContain("<style nonce=\"{$nonce}\">");
});

test('laravel\'s interactive debug renderer for a genuine uncaught exception is exempted from CSP, since it can\'t be nonced', function (): void {
    config(['app.debug' => true]);
    Route::get('/__csp_test_uncaught_exception', function (): void {
        throw new RuntimeException('boom');
    })->middleware('web');

    $response = $this->get('/__csp_test_uncaught_exception');

    $response->assertStatus(500);
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
});

test('a 403 raised from within a matched route still carries a matching nonced content security policy', function (): void {
    $community = newCommunity();
    actingAsMember($community);

    $response = $this->get(route('realms.identity-providers', ['realm' => $community->getShortCode()]));

    $response->assertForbidden();

    $csp = $response->headers->get('Content-Security-Policy');
    preg_match('/style-src[^;]*\'nonce-([^\']+)\'/', (string) $csp, $match);
    $nonce = $match[1] ?? null;

    expect($csp)->not->toBeNull()
        ->and($nonce)->not->toBeNull()
        ->and($response->getContent())->toContain("<style nonce=\"{$nonce}\">");
});
