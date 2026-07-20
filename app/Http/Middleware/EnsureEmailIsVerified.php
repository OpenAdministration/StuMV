<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as Middleware;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;

class EnsureEmailIsVerified extends Middleware
{
    /**
     * Same as the stock middleware, except verification.notice is realm-scoped
     * now - resolved from the {realm} route parameter where the request already
     * has one, falling back to the account's own realm column otherwise (e.g.
     * a request with no {realm} segment at all).
     *
     * @param  Closure(Request):mixed  $next
     */
    #[\Override]
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail && ! $request->user()->hasVerifiedEmail())) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice', [
                    'realm' => $this->realmUidFor($request),
                ]));
        }

        return $next($request);
    }

    private function realmUidFor(Request $request): ?string
    {
        $realm = $request->route('realm');

        return match (true) {
            $realm instanceof Community => $realm->getShortCode(),
            is_string($realm) => $realm,
            default => $request->user()?->realm,
        };
    }
}
