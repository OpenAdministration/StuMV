<?php

namespace App\Http\Middleware;

use App\Ldap\User as LdapUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotLocked
{
    /**
     * Discards the session of a user whose account was locked after they
     * already logged in. Login itself is blocked separately by
     * App\Ldap\Rules\DenyLockedUsers.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ldapUser = $user?->ldapOrNull();

        if ($ldapUser && LdapUser::isLockedByUsername($ldapUser->getFirstAttribute('uid'), $ldapUser->getParentDn())) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        return $next($request);
    }
}
