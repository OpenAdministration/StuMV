<?php

namespace App\Services\Oidc;

use App\Models\PassportClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;

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
    public function build(PassportClient $client, string $userId, string $sid): Plain
    {
        $config = Configuration::forSymmetricSigner(
            resolve(config('openid.signer')),
            InMemory::file(Passport::keyPath('oauth-private.key')),
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
            ->issuedBy($this->issuer($client))
            ->permittedFor($client->getKey())
            ->relatedTo($userId)
            ->issuedAt(new DateTimeImmutable)
            ->identifiedBy((string) Str::uuid())
            ->withClaim('sid', $sid)
            ->withClaim('events', [
                'http://schemas.openid.net/event/backchannel-logout' => new \stdClass,
            ])
            ->getToken($config->signer(), $config->signingKey());
    }

    /**
     * Matches App\Http\Controllers\Oidc\RealmDiscoveryController::issuer() -
     * clients validate a logout_token's `iss` claim against the same
     * realm-prefixed issuer their discovery document advertises.
     */
    private function issuer(PassportClient $client): string
    {
        return rtrim(URL::to('/'.$client->community_uid), '/');
    }
}
