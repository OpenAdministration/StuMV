<?php

namespace App\Http\Controllers\Api\Directory\Concerns;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Support\Facades\Auth;

trait AuthorizesDirectoryClient
{
    /**
     * The "client" middleware already guarantees the token has no human
     * resource owner; this confirms the authenticated client is the one
     * registered for the community being queried.
     */
    protected function authorizeClientForCommunity(Community $community): void
    {
        /** @var PassportClient|null $client */
        $client = Auth::guard('api')->client();

        abort_unless($client?->community_uid === $community->getShortCode(), 403);
    }
}
