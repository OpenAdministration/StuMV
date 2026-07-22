<?php

namespace App\Services\Oidc;

use Lcobucci\JWT\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use OpenIDConnect\IdTokenResponse as BaseIdTokenResponse;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

/**
 * Adds a `sid` claim the base package doesn't emit - RealmDiscoveryController
 * advertises backchannel_logout_session_supported: true, which per spec
 * requires the ID token and the later logout_token
 * (App\Services\Oidc\BackChannelLogoutTokenBuilder) to carry the same `sid`
 * so a relying party can correlate which of a user's sessions ended. The
 * access token identifier already equals the persisted Token::id
 * (Laravel\Passport\Bridge\AccessTokenRepository::persistNewAccessToken()
 * saves it as the row's primary key before this response is built), which is
 * exactly the value BackChannelLogoutTokenBuilder is given at logout time.
 */
class IdTokenResponse extends BaseIdTokenResponse
{
    protected function getBuilder(
        AccessTokenEntityInterface $accessToken,
        IdentityEntityInterface $userEntity
    ): Builder {
        return parent::getBuilder($accessToken, $userEntity)
            ->withClaim('sid', $accessToken->getIdentifier());
    }
}
