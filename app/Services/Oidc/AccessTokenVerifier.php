<?php

namespace App\Services\Oidc;

use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Verifies a raw bearer access token string outside of a real incoming
 * request - used by both IntrospectionController and RevocationController,
 * which each need to check an arbitrary token-in-a-parameter rather than the
 * current request's own Authorization header. Builds a throwaway PSR-7
 * request carrying that token as its own Bearer header and hands it to the
 * same ResourceServer every other authenticated API request already goes
 * through (see Laravel\Passport\Guards\TokenGuard::getPsrRequestViaBearerToken()),
 * so signature, expiry and revocation are checked with the exact same,
 * already-vetted logic rather than reimplementing JWT verification here.
 */
class AccessTokenVerifier
{
    public function __construct(private readonly ResourceServer $resourceServer) {}

    public function verify(string $token): ?AccessToken
    {
        $psrRequest = (new PsrHttpFactory)->createRequest(
            Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token])
        );

        try {
            return AccessToken::fromPsrRequest($this->resourceServer->validateAuthenticatedRequest($psrRequest));
        } catch (OAuthServerException) {
            return null;
        }
    }
}
