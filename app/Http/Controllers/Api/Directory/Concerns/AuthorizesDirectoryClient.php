<?php

namespace App\Http\Controllers\Api\Directory\Concerns;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Guards\TokenGuard;

trait AuthorizesDirectoryClient
{
    /**
     * The "client" middleware already guarantees the token has no human
     * resource owner; this confirms the authenticated client is the one
     * registered for the community being queried.
     */
    protected function authorizeClientForCommunity(Community $community): void
    {
        $guard = Auth::guard('api');
        $client = $guard instanceof TokenGuard ? $guard->client() : null;

        abort_unless($client instanceof PassportClient && $client->community_uid === $community->getShortCode(), 403);
    }
}
