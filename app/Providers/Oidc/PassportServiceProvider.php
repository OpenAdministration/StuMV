<?php

namespace App\Providers\Oidc;

use App\Services\Oidc\IdTokenResponse;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\Claims\ClaimSet;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;
use OpenIDConnect\Laravel\PassportServiceProvider as BasePassportServiceProvider;

/**
 * Registered (config/app.php) in place of the vendor package's own
 * OpenIDConnect\Laravel\PassportServiceProvider - identical except it mints
 * App\Services\Oidc\IdTokenResponse instead of the base package's
 * OpenIDConnect\IdTokenResponse, so ID tokens carry the `sid` claim
 * BackChannelLogoutTokenBuilder's logout_token also carries.
 */
class PassportServiceProvider extends BasePassportServiceProvider
{
    public function makeAuthorizationServer(?ResponseTypeInterface $responseType = null): AuthorizationServer
    {
        $cryptKey = $this->makeCryptKey('private');
        $encryptionKey = $this->getEncryptionKey(app(Encrypter::class)->getKey());

        $customClaimSets = config('openid.custom_claim_sets');

        $claimSets = array_map(
            fn ($claimSet, $name) => new ClaimSet($name, $claimSet),
            $customClaimSets,
            array_keys($customClaimSets),
        );

        $responseType = new IdTokenResponse(
            app(config('openid.repositories.identity')),
            new ClaimExtractor(...$claimSets),
            Configuration::forSymmetricSigner(
                app(config('openid.signer')),
                InMemory::plainText($cryptKey->getKeyContents(), $cryptKey->getPassPhrase() ?? ''),
            ),
            config('openid.token_headers'),
            config('openid.use_microseconds'),
            app(LaravelCurrentRequestService::class),
            $encryptionKey,
            config('openid.issuedBy', 'laravel'),
        );

        return new AuthorizationServer(
            app(ClientRepository::class),
            app(AccessTokenRepository::class),
            app(Passport\Bridge\ScopeRepository::class),
            $cryptKey,
            $encryptionKey,
            $responseType,
        );
    }
}
