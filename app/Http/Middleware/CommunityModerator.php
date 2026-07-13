<?php

namespace App\Http\Middleware;

use App\Ldap\Committee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CommunityModerator
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = Route::current()->parameter('uid');
        $ou = Route::current()->parameter('ou');

        // Routes that carry a specific committee (e.g. editing it, or one of
        // its roles/memberships) are scoped to that committee - a committee
        // moderator's authority only covers this committee and its
        // descendants, not the whole community. Routes with no committee yet
        // (e.g. picking a parent for a brand new one) fall back to the
        // coarser "moderates something in this community" check.
        if ($ou !== null) {
            $committee = Committee::findByName($community->getShortCode(), $ou) ?? abort(404);
            $allowed = $request->user()->can('moderator', [$committee, $community]);
        } else {
            $allowed = $request->user()->can('moderator', $community)
                || $community->hasCommitteeModeratorSomewhere($request->user());
        }

        if (! $allowed) {
            abort(403);
        }

        return $next($request);
    }
}
