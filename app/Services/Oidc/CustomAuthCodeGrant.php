<?php

namespace App\Services\Oidc;

use League\OAuth2\Server\Grant\AuthCodeGrant as LeagueAuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Nyholm\Psr7\Response;
use OpenIDConnect\Grant\AuthCodeGrant as BaseAuthCodeGrant;
use ReflectionProperty;

/**
 * Two customizations on top of OpenIDConnect\Grant\AuthCodeGrant (itself
 * only adding `nonce` support to League's own AuthCodeGrant):
 *
 * - PKCE is restricted to S256. League\OAuth2\Server\Grant\AuthCodeGrant
 *   unconditionally registers a PlainVerifier alongside S256Verifier in its
 *   constructor (both fully `private`, no constructor argument or public
 *   setter to opt out - see $codeChallengeVerifiers there) - "plain" just
 *   echoes the verifier back as-is, defeating the entire point of PKCE
 *   (protecting the authorization code from an attacker who intercepted it,
 *   e.g. via a malicious app registering the same custom URI scheme on the
 *   same device - exactly what PKCE exists for on native/mobile clients).
 *   Since the base class exposes no supported way to drop it, the
 *   constructor removes the "plain" entry from the private property via
 *   reflection right after construction.
 *
 * - `auth_time` (when the user actually logged in - see App\Http\Middleware\
 *   EnforceMaxAge and every login path that stamps session('auth_time')) is
 *   propagated into the auth code payload the same way this class's parent
 *   already does for `nonce`: decrypt the code embedded in the redirect
 *   response, add a field, re-encrypt, patch the redirect. It has to travel
 *   this way (rather than being read directly from session() at id_token
 *   time) because /oauth/token is normally called server-to-server by the
 *   client's own backend, with no user session/cookie attached at all -
 *   App\Services\Oidc\IdTokenResponse::getBuilder() picks this same field
 *   back out of the auth code to stamp the id_token's `auth_time` claim.
 */
class CustomAuthCodeGrant extends BaseAuthCodeGrant
{
    public function __construct(...$args)
    {
        parent::__construct(...$args);

        $property = new ReflectionProperty(LeagueAuthCodeGrant::class, 'codeChallengeVerifiers');
        $property->setAccessible(true);

        $verifiers = $property->getValue($this);
        unset($verifiers['plain']);
        $property->setValue($this, $verifiers);
    }

    #[\Override]
    public function completeAuthorizationRequest(AuthorizationRequestInterface $authorizationRequest): ResponseTypeInterface
    {
        /** @var RedirectResponse $response */
        $response = parent::completeAuthorizationRequest($authorizationRequest);

        $authTime = session('auth_time');

        if ($authTime === null) {
            return $response;
        }

        $redirectUri = $response->generateHttpResponse(new Response)->getHeader('Location')[0];

        parse_str((string) parse_url($redirectUri, PHP_URL_QUERY), $query);

        if (! isset($query['code'])) {
            return $response;
        }

        $authCodePayload = json_decode($this->decrypt($query['code']), true, 512, JSON_THROW_ON_ERROR);
        $authCodePayload['auth_time'] = $authTime;
        $newCode = $this->encrypt(json_encode($authCodePayload, JSON_THROW_ON_ERROR));

        $response->setRedirectUri(preg_replace('/([?&]code=)[^&]+/', '$1'.rawurlencode($newCode), $redirectUri, 1));

        return $response;
    }
}
