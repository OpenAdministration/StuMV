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
     */
    #[\Override]
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return ! $this->requires_consent;
    }
}
