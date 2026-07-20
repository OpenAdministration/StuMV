<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated
     * (e.g. their session timed out). Sends them back to their own realm's login
     * page rather than the generic picker, so they don't have to re-pick their
     * community - resolved from the {realm} route parameter, which at this point
     * (auth runs before SubstituteBindings, see $middlewarePriority) is still the
     * raw uid string rather than the resolved Community model.
     *
     * @param  Request  $request
     * @return string|null
     */
    #[\Override]
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $realm = $request->route('realm');

            return Community::loginUrlFor($realm instanceof Community ? $realm->getShortCode() : $realm);
        }
    }
}
