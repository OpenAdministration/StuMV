<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SetContentSecurityPolicy
{
    /**
     * script-src needs neither 'unsafe-inline' nor 'unsafe-eval': Alpine runs
     * in its CSP-safe build (config/livewire.php: csp_safe), which evaluates
     * directive expressions through its own restricted parser instead of
     * `new Function()`. That parser can't handle arrow-function/method-object
     * literals though (confirmed live: throws "Unexpected token" on exactly
     * that shape) - which rules out inline `x-data="{ ... methods ... }"` and
     * Livewire's @script blocks for anything beyond trivial statements. Every
     * Alpine component with actual behavior (the cropper, the breadcrumbs bar,
     * the persisted "show only mine/active" toggles) is therefore registered
     * via Alpine.data() in resources/js/app.js - a plain bundled file, never
     * touching that parser - and referenced from Blade only by name or a call
     * with literal arguments (`x-data="cropper"`,
     * `x-data="persistedToggle('showOnlyMine', 'realms.showOnlyMine', false)"`),
     * which the restricted parser handles fine. Flux itself did the same
     * refactor for its own components in v2.11.0 (see
     * https://github.com/livewire/flux/pull/2277); this app is on v2.15.0.
     *
     * style-src needs 'unsafe-inline': cropperjs (profile picture cropping)
     * and the breadcrumbs bar inject their own <style> elements/shadow-DOM
     * stylesheets that this app doesn't control and can't attach a nonce to.
     *
     * The nonce is generated here via Vite::useCspNonce() (Laravel's own
     * mechanism), because Flux's @fluxAppearance directive (dark-mode
     * detection, applied before first paint) is a genuine inline <script> -
     * it needs @fluxAppearance(['nonce' => Vite::cspNonce()]) in the layout
     * to pick this same nonce up. Livewire's own inline tags already default
     * to Vite::cspNonce() with no per-view change needed.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
