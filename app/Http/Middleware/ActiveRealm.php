<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Closure;
use Illuminate\Http\Request;

class ActiveRealm
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
        if (!session()->exists('realm_uid')){
            $realms = Community::query()
                ->list() // only first level
                ->setDn(Community::$rootDn)->get('ou');
            if($realms->count() !== 1){
                return redirect()->route('realms.pick')
                    ->with('status', 'warning')
                    ->with('message', __('Enter a Dungeon first'));
            }
            session(['realm_uid' => $realms->first()->getFirstAttribute('ou')]);
        }
        return $next($request);
    }


}
