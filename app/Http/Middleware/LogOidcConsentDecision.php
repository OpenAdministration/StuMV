<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Passport's own ApproveAuthorizationController/DenyAuthorizationController
 * (required in wholesale from vendor/laravel/passport/routes/web.php, see
 * routes/web.php's "realm.passport." group) dispatch no event and can't be
 * subclassed without replacing that whole route group - this reads the
 * pending AuthorizationRequest Passport itself already serialized into the
 * session (AuthorizationController.php: $request->session()->put('authRequest', ...))
 * the same way Laravel\Passport\Http\Controllers\RetrievesAuthRequestFromSession
 * does, but with get() instead of pull() so it doesn't consume it before the
 * real controller runs.
 */
class LogOidcConsentDecision
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('OIDC consent '.$this->decision($request), [
            'realm' => $request->route('realm')?->getShortCode(),
            'client_id' => $this->clientIdFromPendingAuthRequest($request),
            'user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }

    private function decision(Request $request): string
    {
        return str_ends_with((string) $request->route()?->getName(), '.approve') ? 'approved' : 'denied';
    }

    private function clientIdFromPendingAuthRequest(Request $request): ?string
    {
        $serialized = $request->session()->get('authRequest');

        if (! is_string($serialized)) {
            return null;
        }

        try {
            $authRequest = unserialize($serialized, ['allowed_classes' => [
                AuthorizationRequest::class,
                Client::class,
                Scope::class,
                User::class,
            ]]);

            return $authRequest instanceof AuthorizationRequest
                ? $authRequest->getClient()->getIdentifier()
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
