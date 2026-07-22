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
     * style-src needs neither 'unsafe-inline' nor a broad style nonce
     * exemption: cropperjs (profile picture cropping) injects its shadow-DOM
     * styles via adoptedStyleSheets (see $addStyles() in cropperjs's source),
     * which isn't subject to style-src at all - only the legacy <style>
     * element/style attribute fallback path would be, and every evergreen
     * browser supports Constructible Stylesheets. The breadcrumbs bar's own
     * <style> block is static and nonced directly
     * (resources/views/vendor/breadcrumbs/tailwind.blade.php). The guest
     * layout's per-realm branding background image is a nonced <style> block
     * too, not an inline style="" attribute (nonces don't cover the style
     * attribute, only <style>/<script> elements) - see
     * resources/views/layouts/guest.blade.php. Laravel's own error pages
     * (resources/views/errors/minimal.blade.php, overriding the vendor
     * default) get the same treatment for their two <style> blocks.
     *
     * The nonce is generated here via Vite::useCspNonce() (Laravel's own
     * mechanism), because Flux's @fluxAppearance directive (dark-mode
     * detection, applied before first paint) is a genuine inline <script> -
     * it needs @fluxAppearance(['nonce' => Vite::cspNonce()]) in the layout
     * to pick this same nonce up. Livewire's own inline tags already default
     * to Vite::cspNonce() with no per-view change needed.
     *
     * The header itself is deliberately NOT set here after $next($request) -
     * see apply() below for why, and AppServiceProvider::boot() for where
     * apply() actually gets invoked.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        return $next($request);
    }

    /**
     * Builds the policy and stamps it onto $response. Called from a
     * RequestHandled listener (AppServiceProvider::boot()), not from
     * handle() above: a 404 for a URL that matches no route at all never
     * reaches this middleware (route-group middleware, including this one,
     * only runs once a route has actually matched), and other error
     * responses (403/419/500/a 404 from route-model binding) are rendered
     * by the exception handler and returned as the result of $next($request)
     * from *inside* the route pipeline - by which point setting the header
     * here would still work for those, but not for the no-route-matched
     * case. RequestHandled fires unconditionally once any response, from
     * either path, is ready to send - Vite::cspNonce() (not useCspNonce())
     * returns whatever nonce is already in play for this request, generated
     * either by handle() above or lazily by the first Blade view that
     * referenced it (e.g. resources/views/errors/minimal.blade.php, for the
     * no-route-matched case where handle() never ran).
     */
    public static function apply(Response $response): void
    {
        // Laravel's own interactive debug renderer (Illuminate\Foundation\
        // Exceptions\Renderer\Renderer, used locally whenever APP_DEBUG is on
        // and a genuine non-HTTP exception occurs - always a 500) inlines its
        // CSS/JS via Renderer::css()/js(), which hardcode plain <style>/
        // <script> tags with no nonce support and no hook to add one. It
        // never renders in production (APP_DEBUG is always off there, and
        // even locally only for this exact case - a deliberate abort(500,...)
        // still renders through the app's own nonced errors::minimal), so
        // it's exempted here rather than left permanently broken.
        if (config('app.debug') && $response->getStatusCode() === 500) {
            return;
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".Vite::cspNonce()."'",
            "style-src 'self' 'nonce-".Vite::cspNonce()."'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
    }
}
