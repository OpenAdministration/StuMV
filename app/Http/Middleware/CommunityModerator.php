<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class CommunityModerator
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = Route::current()->parameter('uid');
        if($request->user()->cannot('moderator', $community)){
            abort(403);
        }
        return $next($request);
    }
}
