<?php

namespace App\Services\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use Lcobucci\JWT\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use OpenIDConnect\IdTokenResponse as BaseIdTokenResponse;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

/**
 * Overrides two things the base package gets wrong for a realm-scoped OP:
 *
 * - `iss`: the base package's IssuedByGetter (config('openid.issuedBy'),
 *   defaulting to 'laravel') stamps just the app's scheme+host - it has no
 *   notion of realms at all. App\Http\Controllers\Oidc\RealmDiscoveryController
 *   advertises a realm-scoped issuer instead (App\Ldap\Community::issuerFor()),
 *   and a spec-compliant relying party that validates the id_token's `iss`
 *   against its discovery document's `issuer` (as Nextcloud's user_oidc does)
 *   rejects the token outright on that mismatch - it never surfaces as
 *   anything other than a generic "issuer doesn't match" error client-side,
 *   with nothing logged on this end, since nothing here ever threw.
 * - `sid`: the base package doesn't emit one at all.
 *   RealmDiscoveryController advertises backchannel_logout_session_supported:
 *   true, which per spec requires the id_token and the later logout_token
 *   (App\Services\Oidc\BackChannelLogoutTokenBuilder) to carry the same `sid`
 *   so a relying party can correlate which of a user's sessions ended. The
 *   access token identifier already equals the persisted Token::id
 *   (Laravel\Passport\Bridge\AccessTokenRepository::persistNewAccessToken()
 *   saves it as the row's primary key before this response is built), which is
 *   exactly the value BackChannelLogoutTokenBuilder is given at logout time.
 */
class IdTokenResponse extends BaseIdTokenResponse
{
    #[\Override]
    protected function getBuilder(
        AccessTokenEntityInterface $accessToken,
        IdentityEntityInterface $userEntity
    ): Builder {
        $builder = parent::getBuilder($accessToken, $userEntity)
            ->withClaim('sid', $accessToken->getIdentifier());

        $client = PassportClient::find($accessToken->getClient()->getIdentifier());

        if ($client !== null) {
            $builder = $builder->issuedBy(Community::issuerFor($client->community_uid));
        }

        return $builder;
    }
}
