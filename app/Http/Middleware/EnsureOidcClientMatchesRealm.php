<?php

namespace App\Http\Middleware;

use App\Ldap\Community;
use App\Models\PassportClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        if ($clientId === null) {
            Log::warning('OIDC request rejected: no client_id given', ['realm' => $realm->getShortCode(), 'path' => $request->path()]);
            abort(401);
        }

        $client = PassportClient::find($clientId);

        if ($client === null) {
            Log::warning('OIDC request rejected: unknown client_id', ['realm' => $realm->getShortCode(), 'client_id' => $clientId, 'path' => $request->path()]);
            abort(401);
        }

        if ($client->community_uid !== $realm->getShortCode()) {
            Log::warning('OIDC request rejected: client bound to a different realm', [
                'realm' => $realm->getShortCode(),
                'client_id' => $clientId,
                'client_realm' => $client->community_uid,
                'path' => $request->path(),
            ]);
            abort(403);
        }

        return $next($request);
    }
}
