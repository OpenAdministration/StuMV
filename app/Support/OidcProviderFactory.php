<?php

namespace App\Support;

use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Thin wrapper around `new GenericProvider(...)` so tests can swap in a
 * GenericProvider backed by a mocked Guzzle handler - league/oauth2-client
 * uses its own internal HTTP client for the token/userinfo exchange rather
 * than Laravel's Http facade, so Http::fake() alone cannot intercept it.
 */
class OidcProviderFactory
{
    public function make(array $options): GenericProvider
    {
        return new GenericProvider($options);
    }
}
