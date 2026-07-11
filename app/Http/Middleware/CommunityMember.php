<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommunityMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):((Response|RedirectResponse))  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $community = $request->route('uid');
        if (! ($community instanceof Community)) {
            abort(404);
        }
        if ($request->user()->cannot('enter', $community)) {
            abort(403);
        }

        return $next($request);
    }
}
