<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;

/**
 * PreventRequestForgery::handle() always passes every request through during
 * a Pest/PHPUnit run - runningUnitTests() short-circuits it regardless of
 * $except - so an actual HTTP request can never exercise real CSRF
 * enforcement here. Checking the configured exemption patterns directly is
 * the only way to catch a regression like the one this file guards against:
 * an OIDC client's server-to-server /oauth/token call (no session, no CSRF
 * token to give) getting rejected with 419 right after an otherwise
 * successful login+consent, because routes/web.php's "web" middleware group
 * wraps Passport's own routes/web.php too - even though Passport itself
 * deliberately registers /token et al. with only 'throttle', not 'web'.
 */
test('the OIDC/OAuth machine endpoints are exempt from CSRF verification', function (): void {
    $excluded = new VerifyCsrfToken(app(), resolve('encrypter'))->getExcludedPaths();

    foreach ([
        'demo/oauth/token',
        'demo/oauth/token/refresh',
        'demo/oauth/device/code',
        'demo/oauth/end-session',
    ] as $path) {
        expect(collect($excluded)->contains(fn (string $pattern): bool => Str::is($pattern, $path)))
            ->toBeTrue("$path should be CSRF-exempt");
    }
});

test('the browser-facing OAuth authorization endpoints still require CSRF verification', function (): void {
    $excluded = new VerifyCsrfToken(app(), resolve('encrypter'))->getExcludedPaths();

    foreach ([
        'demo/oauth/authorize',
        'demo/oauth/device/authorize',
    ] as $path) {
        expect(collect($excluded)->contains(fn (string $pattern): bool => Str::is($pattern, $path)))
            ->toBeFalse("$path should still require CSRF verification");
    }
});
