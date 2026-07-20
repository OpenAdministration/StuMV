<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;

/**
 * Realm-prefixed replacement for OpenIDConnect\Laravel\DiscoveryController -
 * that one hardcodes the global Passport/OpenID route names
 * (passport.authorizations.authorize, passport.token, openid.userinfo,
 * openid.jwks), which no longer exist now that OIDC endpoints are
 * realm-prefixed (see routes/web.php's "realm.passport." and "realm.openid."
 * route groups). Otherwise mirrors it exactly, including its OpenID Connect
 * Discovery 1.0 §3 shape.
 */
class RealmDiscoveryController extends Controller
{
    public function __invoke(Request $request, Community $realm)
    {
        if (config('openid.forceHttps', true)) {
            URL::forceScheme('https');
        }

        // Community isn't Illuminate\Contracts\Routing\UrlRoutable - passing
        // the model itself to route() falls back to its __toString(), which
        // returns the full DN, not the short code the {realm} segment
        // actually expects. Pass the short code explicitly instead.
        $uid = $realm->getShortCode();

        $response = [
            'issuer' => $this->issuer($realm),
            'authorization_endpoint' => route('realm.passport.authorizations.authorize', ['realm' => $uid]),
            'token_endpoint' => route('realm.passport.token', ['realm' => $uid]),
            'userinfo_endpoint' => route('realm.openid.userinfo', ['realm' => $uid]),
            'jwks_uri' => route('realm.openid.jwks', ['realm' => $uid]),
            'grant_types_supported' => $this->getSupportedGrantTypes(),
            'response_types_supported' => $this->getSupportedResponseTypes(),
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => $this->getSupportedScopes(),
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
            ],
            'code_challenge_methods_supported' => [
                'plain',
                'S256',
            ],
        ];

        return response()->json($response, 200, [], JSON_PRETTY_PRINT);
    }

    private function issuer(Community $realm): string
    {
        return rtrim(URL::to('/'.$realm->getShortCode()), '/');
    }

    /**
     * Same list config('openid.passport.tokens_can') exposes globally - OIDC
     * clients aren't currently scoped to a subset of these per realm.
     */
    private function getSupportedScopes(): array
    {
        $scopes = array_keys(config('openid.passport.tokens_can'));

        if (! config('openid.discovery.hide_scopes', false)) {
            return $scopes;
        }

        return array_intersect($scopes, ['openid', 'profile', 'email', 'address', 'phone']);
    }

    private function getSupportedGrantTypes(): array
    {
        $grants = [
            'authorization_code',
            'client_credentials',
            'refresh_token',
        ];

        if (Passport::$implicitGrantEnabled) {
            $grants[] = 'implicit';
        }

        if (Passport::$passwordGrantEnabled) {
            $grants[] = 'password';
        }

        return $grants;
    }

    private function getSupportedResponseTypes(): array
    {
        $responseTypes = ['code'];

        if (Passport::$implicitGrantEnabled) {
            return array_merge($responseTypes, ['token']);
        }

        return $responseTypes;
    }
}
