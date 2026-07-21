<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const array AVAILABLE_LOCALES = ['de', 'en'];

    /**
     * This used to live as top-level code in routes/web.php, run once when
     * routes are registered (RouteServiceProvider's booted() callback) -
     * not per request. Whatever Request happened to be bound in the
     * container at that single moment decided the locale for the app's
     * entire lifetime, regardless of what any individual request's own
     * Accept-Language header said (in a fresh-process-per-request PHP-FPM
     * deployment this is usually invisible - boot and the one real request
     * coincide - but it broke outright under any longer-lived process, and
     * made this logic untestable). Middleware runs fresh for every request,
     * so it always sees that request's own headers.
     *
     * App::setLocale() (not just Config::set('app.locale', ...)) matters
     * too: AppServiceProvider::boot() already resolves the translator
     * singleton earlier in the boot cycle (via Lang::handleMissingKeysUsing(),
     * gated on debug mode) - only App::setLocale() re-propagates to an
     * already-resolved translator instance.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::AVAILABLE_LOCALES);

        if ($locale) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
