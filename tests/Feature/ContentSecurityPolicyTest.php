<?php

use App\Models\RealmBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
