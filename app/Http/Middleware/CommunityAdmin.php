<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CommunityAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = $request->route()?->parameter('uid');
        $user = $request->user();
        if($user?->can('admin', $community) || $user?->ldap()->isSuperAdmin()){
            return $next($request);
        }
        abort(403);
    }
}
