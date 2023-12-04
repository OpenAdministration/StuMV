<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
            // auto-pick if unset
            $realms = Community::query()
                ->list() // only first level
                ->setDn(Community::$rootDn)->get();
            if($realms->count() !== 1){
                return redirect()->route('realms.pick')
                    ->with('status', 'warning')
                    ->with('message', __('Enter a Dungeon first'));
            }
            // enter the community
            $community  = $realms->first();
            $request->user()->can('enter', $community);
            // this session entry might be needed in some livewire components,
            // where their routes do not have realm uid / community
            session(['realm_uid' => $community->getFirstAttribute('ou')]);
        }
        return $next($request);
    }


}
