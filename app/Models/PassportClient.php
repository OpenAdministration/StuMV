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
    #[\Override]
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
     * been granted a token covering the same scopes - mirrors
     * Laravel\Passport\Http\Controllers\AuthorizationController::hasGrantedScopes(),
     * except it doesn't require that token to still be within its expiry
     * window. Passport's own version only considers currently-active
     * tokens, which would force a fresh prompt on every access-token expiry
     * even though nothing about the actual grant changed; EditOidcClient::save()
     * already revokes every one of this client's tokens as soon as its
     * scopes change, so "not revoked" alone is exactly the signal that
     * should invalidate remembered consent, not the passage of time.
     */
    #[\Override]
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        if (! $this->requires_consent) {
            return true;
        }

        return $this->hasGrantedScopes($user, $scopes);
    }

    private function hasGrantedScopes(Authenticatable $user, array $scopes): bool
    {
        $grantedTokens = $this->tokens()->where([
            ['user_id', '=', $user->getAuthIdentifier()],
            ['revoked', '=', false],
        ]);

        if (empty($scopes)) {
            return $grantedTokens->exists();
        }

        return collect($scopes)->pluck('id')->diff(
            $grantedTokens->pluck('scopes')->flatten()
        )->isEmpty();
    }
}
