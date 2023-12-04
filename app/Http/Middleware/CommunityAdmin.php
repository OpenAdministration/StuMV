<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CommunityAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = $request->route()?->parameter('uid');
        $user = auth()->user();
        if($user?->can('admin', $community) || $user?->ldap()->isSuperAdmin()){
            return $next($request);
        }
        abort(403);
    }
}
