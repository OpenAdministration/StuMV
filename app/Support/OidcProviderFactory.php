<?php

namespace App\Support;

use Illuminate\Http\Request;
use SocialiteProviders\Manager\Config;
use SocialiteProviders\OpenIDConnect\Provider;
use stdClass;

/**
 * Builds a Provider instance per RealmIdentityProvider row, at request time -
 * the package's own docs only cover a small, fixed set of named connections
 * declared in config/oidc.php, but StuMV's realm-level identity providers are
 * an admin-managed, unbounded set of DB rows chosen at request time, so
 * there's nothing to declare ahead of time. A thin wrapper (rather than
 * constructing Provider directly in the controller) so tests can inject a
 * mocked Guzzle handler - this package builds its own internal Guzzle client
 * for every OIDC request (discovery, JWKS, token, userinfo), which
 * Http::fake() cannot intercept.
 *
 * @param  array{client_id: string, client_secret: string, redirect: string, base_url: string, scopes: ?string, require_email?: bool}  $config
 */
class OidcProviderFactory
{
    /** Tolerated clock difference, in seconds, between this server and the provider. */
    private const int CLOCK_SKEW_LEEWAY = 60;

    /**
     * @param  array  $guzzle  Extra Guzzle client options for every Provider
     *                         this factory builds - empty in production, a
     *                         mocked handler in tests (see tests/Pest.php's
     *                         fakeIdentityProviderHttp()), so both share the
     *                         exact same config/issuer-validation logic below
     *                         instead of tests re-implementing (and risking
     *                         drifting from) it.
     */
    public function __construct(private readonly array $guzzle = []) {}

    public function make(Request $request, array $config): Provider
    {
        $provider = new Provider(
            $request,
            $config['client_id'],
            $config['client_secret'],
            $config['redirect'],
            $this->guzzle,
        );

        $provider->setConfig(new Config(
            $config['client_id'],
            $config['client_secret'],
            $config['redirect'],
            [
                ...$config,
                // The package trusts whatever issuer the (possibly forged)
                // discovery document names unless told otherwise - pinning it
                // to the realm admin's own configured value is what actually
                // stops a compromised/spoofed discovery document from
                // substituting a different, attacker-controlled issuer.
                'issuer' => rtrim($config['base_url'], '/'),
                'clock_skew' => $config['clock_skew'] ?? self::CLOCK_SKEW_LEEWAY,
            ],
        ));

        // The package's default issuer check is strict equality (OIDC Core
        // 3.1.3.7 step 2), but real IdPs deviate: Auth0 issues tokens with a
        // trailing slash the configured issuer doesn't carry, and Google
        // spells its own issuer without a scheme in some responses. Neither
        // weakens the check itself - the signature is already verified
        // against the JWKS the pinned issuer's own discovery document named.
        return $provider->validateIssuerUsing(function (string $expectedIssuer, stdClass $payload): bool {
            $issuer = $payload->iss ?? null;

            if (! is_string($issuer) || $issuer === '') {
                return false;
            }

            $issuer = rtrim($issuer, '/');
            $expectedIssuer = rtrim($expectedIssuer, '/');

            return $issuer === $expectedIssuer
                || 'https://'.$issuer === $expectedIssuer
                || $issuer === 'https://'.$expectedIssuer;
        });
    }
}
