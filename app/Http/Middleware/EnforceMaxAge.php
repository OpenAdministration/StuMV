<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Exceptions\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * OIDC Core 1.0 §3.1.2.1: `max_age` asks the OP to force re-authentication
 * if the end-user's last authentication is older than the given number of
 * seconds. Mirrors exactly how Passport's own AuthorizationController
 * handles `prompt=login` (logout + invalidate the session + regenerate the
 * CSRF token, then throw its own AuthenticationException) - this app's
 * AuthenticationException::redirectUsing() customization
 * (App\Providers\AppServiceProvider::boot()) already sends that exception to
 * this realm's own login page, and Laravel's default redirect()->guest()
 * call stores the current full URL - this /authorize request, max_age and
 * all - as the post-login "intended" redirect
 * (App\Http\Controllers\Auth\AuthenticatedSessionController::store() already
 * honours that via redirect()->intended()), so the user lands right back
 * here with a fresh session('auth_time') once they've re-authenticated.
 */
class EnforceMaxAge
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxAge = $request->query('max_age');

        if ($maxAge !== null && Auth::check()) {
            $authTime = $request->session()->get('auth_time');
            $tooOld = $authTime === null || (time() - (int) $authTime) > (int) $maxAge;

            if ($tooOld) {
                Auth::guard()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new AuthenticationException;
            }
        }

        return $next($request);
    }
}
