<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use App\Models\PassportClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OIDC clients are bound to the realm they were registered under
 * (PassportClient::community_uid) - only accounts authenticating through
 * that same realm's {realm}/oauth/* endpoints may use them. Applied to the
 * realm-prefixed authorize/token routes, comparing the resolved client's own
 * community_uid against the {realm} route parameter, the same pattern
 * DenyAdminRealm already uses for the community-management routes.
 */
class EnsureOidcClientMatchesRealm
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $realm = $request->route('realm');
        abort_unless($realm instanceof Community, 404);

        $clientId = $request->input('client_id') ?? $request->getUser();
        abort_if($clientId === null, 401);

        $client = PassportClient::find($clientId);
        abort_if($client === null, 401);

        abort_unless($client->community_uid === $realm->getShortCode(), 403);

        return $next($request);
    }
}
