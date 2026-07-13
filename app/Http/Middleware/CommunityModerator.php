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

        // Every route this middleware guards is a role/role-membership
        // action scoped to a specific committee - a committee moderator's
        // authority covers this committee and its descendants, not the whole
        // community. Committee create/edit/delete themselves are gated
        // separately (community-moderator-only, see routes/web.php).
        $committee = Committee::findByName($community->getShortCode(), $ou) ?? abort(404);

        if ($request->user()->cannot('moderator', [$committee, $community])) {
            abort(403);
        }

        return $next($request);
    }
}
