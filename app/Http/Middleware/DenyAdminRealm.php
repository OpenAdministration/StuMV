<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dedicated "admin" superadmin realm has no admins/moderators groups,
 * committees, roles, groups, domains, or API/OIDC clients of its own (see
 * Community::generateSkeleton()) - block the routes that manage those
 * outright, rather than letting each one fail confusingly against
 * substructure that was never created.
 */
class DenyAdminRealm
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $realm = $request->route()?->parameter('realm');

        abort_if($realm instanceof Community && $realm->isAdminRealm(), 404);

        return $next($request);
    }
}
