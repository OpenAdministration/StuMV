<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

class PassportClient extends Client
{
    /**
     * Client declares $casts as a plain property (scopes, grant_types,
     * redirect_uris, the bool flags...) - redeclaring that property here
     * would replace, not merge with, its casts. Eloquent merges $casts with
     * casts() additively in initializeHasAttributes(), so this is the safe
     * place to add one.
     */
    protected function casts(): array
    {
        return [
            'requires_consent' => 'bool',
        ];
    }

    /**
     * Determine if the client should skip the authorization prompt.
     * Configurable per client via the requires_consent column (see
     * NewOidcClient/EditOidcClient); defaults to true (show the prompt).
     *
     * When consent is required, still skips it for a user who has already
     * consented to exactly this scope set - checked against
     * oauth_client_consents (see App\Listeners\RecordOidcClientConsent),
     * not against currently-active tokens: Passport's own
     * AuthorizationController::hasGrantedScopes() already provides an
     * active-token-based fallback (still useful and left untouched), but
     * relying on that alone would force a fresh prompt every time a token
     * merely expires, even though nothing about the actual grant changed.
     * This record is only cleared when the client's scopes themselves
     * change (see EditOidcClient::save()).
     */
    #[\Override]
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        if (! $this->requires_consent) {
            return true;
        }

        $consent = OidcClientConsent::where('client_id', $this->id)
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if (! $consent) {
            return false;
        }

        return collect($scopes)->pluck('id')->diff($consent->scopes ?? [])->isEmpty();
    }
}
