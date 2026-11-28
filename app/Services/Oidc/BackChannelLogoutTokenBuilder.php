<?php

namespace App\Services\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;

/**
 * Builds the signed logout_token sent to a client's back_channel_logout_uri
 * per the OpenID Connect Back-Channel Logout 1.0 spec (§2.4).
 */
class BackChannelLogoutTokenBuilder
{
    /**
     * Signed with the same RSA key pair Passport already uses for id_tokens
     * (see App\Providers\Oidc\PassportServiceProvider::makeAuthorizationServer()),
     * so it verifies against the same JWKS StuMV already publishes at
     * {realm}/oauth/jwks.
     *
     * $sid is the Laravel\Passport\Token::id of the specific token/grant that
     * ended - the same value App\Services\Oidc\IdTokenResponse put in the
     * `sid` claim of the id_token that client originally received for this
     * session (both derive from the access token's identifier, which Passport
     * persists as the token row's own primary key), so the client can tell
     * which of a user's sessions this logout_token is for.
     */
    public function build(PassportClient $client, string $userId, string $sid): UnencryptedToken
    {
        if ($userId === '') {
            throw new \InvalidArgumentException('A logout token needs a subject.');
        }
        $realm = $client->community_uid ?? throw new \InvalidArgumentException("Client {$client->getKey()} is not bound to a realm.");

        $config = Configuration::forSymmetricSigner(
            resolve(config('openid.signer')),
            SigningKeyFile::private(),
        );

        $builder = $config->builder();

        // Matches the 'kid' OpenIDConnect\Laravel\JwksController publishes on
        // the JWKS key and OpenIDConnect\IdTokenResponse stamps onto id_tokens
        // (both read the same config('openid.token_headers')) - without it,
        // a relying party whose JWT/JWKS library requires an exact 'kid'
        // match to select a signing key (rather than falling back to "the
        // only key in the set") can't verify this token's signature at all.
        foreach (config('openid.token_headers', []) as $key => $value) {
            $builder = $builder->withHeader($key, $value);
        }

        return $builder
            ->issuedBy(Community::issuerFor($realm))
            ->permittedFor($client->getKey())
            ->relatedTo($userId)
            // Whole seconds, not `new DateTimeImmutable` (which carries
            // microseconds) - lcobucci/jwt's default ChainedFormatter
            // serializes a fractional-second claim as a JSON float, which
            // some relying-party JWT libraries fail to validate at all (see
            // config('openid.use_microseconds')'s doc comment).
            ->issuedAt(new DateTimeImmutable('@'.time()))
            ->identifiedBy((string) Str::uuid())
            ->withClaim('sid', $sid)
            ->withClaim('events', [
                'http://schemas.openid.net/event/backchannel-logout' => new \stdClass,
            ])
            ->getToken($config->signer(), $config->signingKey());
    }
}
