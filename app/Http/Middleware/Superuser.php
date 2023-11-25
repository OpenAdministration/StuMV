<?php

namespace App\Http\Middleware;

use App\Ldap\SuperUserGroup;
use App\Ldap\User;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class Superuser
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected Guard $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $username = $this->auth->user()?->username;
        $u = User::findOrFailByUsername($username);
        $isSuperUser = SuperUserGroup::group()->members()->exists($u);
        if (!$isSuperUser) {
            abort(403, 'Unauthorized action.');
        }
        return $next($request);
    }
}
