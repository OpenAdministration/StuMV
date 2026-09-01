<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenIDConnect\Grant\AuthCodeGrant (vendor) patches the OIDC `nonce`
 * parameter into the just-minted auth code by reading it off whatever HTTP
 * request happens to be "current" the moment completeAuthorizationRequest()
 * actually runs - which is only still the original GET .../authorize
 * (?nonce=...) request when Passport's consent screen is skipped. Whenever
 * consent is shown first, that call instead happens during the later POST
 * to .../approve (see resources/views/auth/oauth/authorize.blade.php's
 * approve form - it only carries auth_token + CSRF, never nonce), so the
 * vendor's own check silently finds nothing and the nonce is lost for good -
 * no error, no log, just a nonce-less id_token.
 *
 * Applied only to the authorize route (routes/web.php), this stashes the
 * nonce into session on that original GET so App\Services\Oidc\CustomAuthCodeGrant
 * can still recover it later regardless of which request ends up completing
 * the authorization - the same problem, and the same fix shape, as
 * App\Http\Middleware\EnforceMaxAge / session('auth_time') already solves
 * for the `auth_time` claim.
 *
 * Cleared when the current request carries no nonce at all, so a stale
 * value from an earlier OIDC flow in the same browser session can't leak
 * into a later, unrelated authorization (e.g. a plain OAuth client that
 * never sends nonce in the first place).
 */
class StashOidcNonce
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('nonce')) {
            $request->session()->put('oidc_nonce', $request->query('nonce'));
        } else {
            $request->session()->forget('oidc_nonce');
        }

        return $next($request);
    }
}
