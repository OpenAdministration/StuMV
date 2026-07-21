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
     * (see OpenIDConnect\Laravel\PassportServiceProvider::makeAuthorizationServer()),
     * so it verifies against the same JWKS StuMV already publishes at
     * {realm}/oauth/jwks.
     *
     * No `sid` claim: StuMV doesn't track a per-browser-session identifier
     * separate from the access/refresh token rows themselves, so `sub` is
     * the only claim identifying which end-user's session ended - the spec
     * only requires that sub or sid be present, not both.
     */
    public function build(PassportClient $client, string $userId): Plain
    {
        $config = Configuration::forSymmetricSigner(
            resolve(config('openid.signer')),
            InMemory::file(Passport::keyPath('oauth-private.key')),
        );

        return $config->builder()
            ->issuedBy($this->issuer($client))
            ->permittedFor($client->getKey())
            ->relatedTo($userId)
            ->issuedAt(new DateTimeImmutable)
            ->identifiedBy((string) Str::uuid())
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
