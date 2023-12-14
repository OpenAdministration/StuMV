<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use App\Ldap\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CommunityMember
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $community = $request->route('uid');
        if(!($community instanceof Community)){
            abort(404);
        }
        if($request->user()->cannot('enter', $community) && $request->user()->cannot('superadmin', User::class)){
            abort(403);
        }
        return $next($request);
    }


}
