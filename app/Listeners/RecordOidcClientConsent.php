<?php

namespace App\Listeners;

use App\Models\OidcClientConsent;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Token;

class RecordOidcClientConsent
{
    /**
     * Records exactly which scopes this user has now been granted for this
     * client, overwriting any previous record - see the
     * oauth_client_consents migration for why this is tracked independently
     * of token expiry. Fires on every access token issued, including ones
     * minted via a refresh token grant (no consent screen involved there
     * either way, so re-recording the same scopes is a harmless no-op) and
     * ones from the auto-approved (skipsAuthorization) path.
     */
    public function handle(AccessTokenCreated $event): void
    {
        if (! $event->userId) {
            return;
        }

        $token = Token::find($event->tokenId);

        if (! $token) {
            return;
        }

        OidcClientConsent::updateOrCreate(
            ['client_id' => $event->clientId, 'user_id' => $event->userId],
            ['scopes' => $token->scopes ?? []]
        );
    }
}
