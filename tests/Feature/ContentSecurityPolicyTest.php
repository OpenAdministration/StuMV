<?php

test('responses carry a same-origin content security policy header', function (): void {
    $response = $this->get(route('login'));

    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toMatch('/script-src[^;]*\'nonce-[^\']+\'/')
        ->not->toContain('unsafe-eval')
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});
