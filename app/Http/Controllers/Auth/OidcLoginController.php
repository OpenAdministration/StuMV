<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\IdentityProviderSession;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\IdentityProviderGroupRoleSync;
use App\Support\IdentityProviderGroupSync;
use App\Support\OidcProviderFactory;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\GenericProvider;
use RuntimeException;
use Throwable;

class OidcLoginController extends Controller
{
    public function __construct(private readonly OidcProviderFactory $providerFactory) {}

    /**
     * Send the visitor to the external identity provider's own login page.
     */
    public function redirect(Community $realm, RealmIdentityProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($realm, $provider);

        $discovery = $this->discover($provider->issuer);
        $oauthProvider = $this->buildProvider($realm, $provider, $discovery);

        // The nonce ties the id_token we'll later receive back to this
        // specific login attempt (replay protection distinct from `state`,
        // which only protects the redirect itself) - verified in
        // verifyIdToken(). getAuthorizationUrl() must run before
        // getPkceCode(), which only has a value once PKCE_METHOD_S256
        // makes getAuthorizationParameters() generate one as a side effect.
        $nonce = Str::random(32);

        $authorizationUrl = $oauthProvider->getAuthorizationUrl([
            'scope' => 'openid email profile',
            'nonce' => $nonce,
        ]);

        session([
            'identity_provider_state' => $oauthProvider->getState(),
            'identity_provider_id' => $provider->id,
            'identity_provider_nonce' => $nonce,
            'identity_provider_pkce_verifier' => $oauthProvider->getPkceCode(),
        ]);

        return redirect($authorizationUrl);
    }

    /**
     * Handle the identity provider's redirect back. Matches the returned
     * email against an existing account in this realm (and logs it in
     * directly - the external IdP already vouched for the address, so no
     * LDAP bind is needed here), or hands off to the "pick a username"
     * completion step for a brand-new account.
     *
     * Not restricted to guests (see routes/auth.php): group/role sync only
     * ever runs as a side effect of this method, so an already-authenticated
     * user has no way to pick up a mapping an admin added after their last
     * login short of this same flow - logging out first only to immediately
     * log back in as themselves. If someone else is already signed in,
     * though, this must never silently switch the session to a different
     * account, so that case is rejected instead.
     */
    public function callback(Community $realm, RealmIdentityProvider $provider, Request $request)
    {
        $this->authorizeProvider($realm, $provider);

        $state = $request->query('state');
        $validState = $state
            && $state === session('identity_provider_state')
            && session('identity_provider_id') === $provider->id;

        $nonce = session('identity_provider_nonce');
        $pkceVerifier = session('identity_provider_pkce_verifier');

        session()->forget([
            'identity_provider_state',
            'identity_provider_id',
            'identity_provider_nonce',
            'identity_provider_pkce_verifier',
        ]);

        abort_unless($validState, 400, 'Invalid or expired SSO login attempt.');
        abort_if($request->has('error'), 400, (string) $request->query('error_description', 'The identity provider reported an error.'));

        $discovery = $this->discover($provider->issuer);
        $oauthProvider = $this->buildProvider($realm, $provider, $discovery);
        $oauthProvider->setPkceCode($pkceVerifier);

        $token = $oauthProvider->getAccessToken('authorization_code', [
            'code' => $request->query('code'),
        ]);

        $idToken = $token->getValues()['id_token'] ?? null;
        abort_if($idToken === null, 422, 'The identity provider did not return an ID token.');

        try {
            $idTokenClaims = $this->verifyIdToken($idToken, $provider, $nonce, $discovery);
        } catch (RuntimeException $runtimeException) {
            abort(400, $runtimeException->getMessage());
        }

        $claims = $oauthProvider->getResourceOwner($token)->toArray();

        // OIDC Core 1.0 5.3.2: the userinfo response's sub must match the
        // id_token's - otherwise a malicious/compromised userinfo endpoint
        // could hand back claims for a different subject than the one that
        // was actually authenticated.
        abort_unless(($claims['sub'] ?? null) === $idTokenClaims['sub'], 422, 'The identity provider returned mismatched subject claims.');

        $email = $claims['email'] ?? null;
        abort_unless($email, 422, 'The identity provider did not return an email address.');

        $existing = User::where('email', $email)->where('realm', $realm->getShortCode())->first();

        if ($existing) {
            abort_if(
                LdapUser::isLockedByUsername($existing->username, $realm->peopleDn()),
                403,
                'This account has been locked.'
            );

            if (Auth::check()) {
                abort_unless(Auth::id() === $existing->id, 409, 'You are already signed in as a different account. Log out first to switch accounts via this identity provider.');
            } else {
                Auth::login($existing);
                $request->session()->regenerate();
                $request->session()->put('auth_time', time());
            }

            resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $existing->username, $claims);
            resolve(IdentityProviderGroupSync::class)->apply($provider, $existing->username, $claims);
            $this->rememberSession($provider, $claims, $request->session()->getId());

            return redirect()->intended(RouteServiceProvider::home($realm->getShortCode()));
        }

        // Same reasoning as the abort_unless() above: an already-authenticated
        // visitor must never be steered toward creating/claiming a different
        // account through this flow. identity-provider.register is a guest
        // route anyway, so without this they'd just be silently bounced back
        // to their own dashboard by RedirectIfAuthenticated, losing the
        // pending registration state with no explanation.
        abort_if(Auth::check(), 409, 'You are already signed in as a different account. Log out first to register a new account via this identity provider.');

        session(['identity_provider_pending' => [
            'realm' => $realm->getShortCode(),
            'provider_id' => $provider->id,
            'email' => $email,
            'given_name' => $claims['given_name'] ?? '',
            'family_name' => $claims['family_name'] ?? '',
            'claims' => $claims,
        ]]);

        return to_route('identity-provider.register', ['realm' => $realm->getShortCode()]);
    }

    /**
     * OpenID Connect Back-Channel Logout 1.0: the external identity provider
     * calls this directly (server-to-server, no browser/session/CSRF token
     * involved) when the user logs out there, so the matching StuMV
     * session(s) - found via the IdentityProviderSession rows written by
     * rememberSession() - can be ended too. Errors are returned as a JSON
     * body per the spec (section 2.6) rather than Laravel's usual abort()
     * pages, since the caller is a machine, not a browser.
     */
    public function backChannelLogout(Community $realm, RealmIdentityProvider $provider, Request $request): JsonResponse
    {
        if (! $provider->enabled || $provider->realm !== $realm->getShortCode()) {
            return $this->backChannelLogoutError('invalid_request', 'Unknown identity provider.');
        }

        $logoutToken = (string) $request->input('logout_token', '');

        if ($logoutToken === '') {
            return $this->backChannelLogoutError('invalid_request', 'Missing logout_token.');
        }

        try {
            $claims = $this->verifyLogoutToken($logoutToken, $provider);
        } catch (RuntimeException $runtimeException) {
            return $this->backChannelLogoutError('invalid_request', $runtimeException->getMessage());
        }

        $sessions = IdentityProviderSession::where('provider_id', $provider->id)
            ->where('external_sub', $claims['sub'])
            ->get();

        foreach ($sessions as $session) {
            resolve('session')->getHandler()->destroy($session->session_id);
        }

        IdentityProviderSession::where('provider_id', $provider->id)
            ->where('external_sub', $claims['sub'])
            ->delete();

        return response()->json([], 200)->header('Cache-Control', 'no-store');
    }

    private function backChannelLogoutError(string $error, string $description): JsonResponse
    {
        return response()->json(['error' => $error, 'error_description' => $description], 400)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Verifies a logout_token per the Back-Channel Logout 1.0 spec: valid
     * signature against the provider's own published JWKS, expected
     * issuer/audience, the required backchannel-logout "events" member, no
     * "nonce" (that's an ID Token-only claim - its presence would suggest a
     * stolen/misused ID token rather than a genuine logout_token), and a
     * "sub" to actually match sessions against.
     *
     * @return array<string, mixed>
     */
    private function verifyLogoutToken(string $logoutToken, RealmIdentityProvider $provider): array
    {
        $discovery = $this->discover($provider->issuer);
        $decoded = $this->verifySignedJwt($logoutToken, $provider, $discovery);

        if (($decoded['iss'] ?? null) !== rtrim($provider->issuer, '/')) {
            throw new RuntimeException('Unexpected issuer in logout_token.');
        }

        $audience = (array) ($decoded['aud'] ?? []);
        if (! in_array($provider->client_id, $audience, true)) {
            throw new RuntimeException('Unexpected audience in logout_token.');
        }

        $events = (array) ($decoded['events'] ?? []);
        if (! array_key_exists('http://schemas.openid.net/event/backchannel-logout', $events)) {
            throw new RuntimeException('Not a back-channel logout event token.');
        }

        if (isset($decoded['nonce'])) {
            throw new RuntimeException('logout_token must not contain a nonce claim.');
        }

        if (empty($decoded['sub'])) {
            throw new RuntimeException('logout_token is missing a sub claim.');
        }

        return $decoded;
    }

    /**
     * OpenID Connect Core 1.0 3.1.3.7 requires validating the id_token
     * returned alongside the access token, not just trusting whatever the
     * userinfo endpoint later hands back over TLS: signature against the
     * provider's own JWKS, expected issuer/audience, and the nonce this app
     * generated for this specific login attempt (replay protection distinct
     * from `state`, which only covers the redirect itself). exp/nbf are
     * checked automatically by JWT::decode().
     *
     * @return array<string, mixed>
     */
    private function verifyIdToken(string $idToken, RealmIdentityProvider $provider, ?string $expectedNonce, array $discovery): array
    {
        $decoded = $this->verifySignedJwt($idToken, $provider, $discovery);

        if (($decoded['iss'] ?? null) !== rtrim($provider->issuer, '/')) {
            throw new RuntimeException('Unexpected issuer in id_token.');
        }

        $audience = (array) ($decoded['aud'] ?? []);
        if (! in_array($provider->client_id, $audience, true)) {
            throw new RuntimeException('Unexpected audience in id_token.');
        }

        if ($expectedNonce === null || ($decoded['nonce'] ?? null) !== $expectedNonce) {
            throw new RuntimeException('Unexpected or missing nonce in id_token.');
        }

        if (empty($decoded['sub'])) {
            throw new RuntimeException('id_token is missing a sub claim.');
        }

        return $decoded;
    }

    /**
     * Decodes and verifies a JWT's signature against the provider's
     * published JWKS, shared by both the id_token (login) and logout_token
     * (back-channel logout) verification paths. Retries once with a forced
     * cache-bust before giving up: the JWKS is cached for an hour (see
     * fetchJwks()), so a provider that rotates its signing keys - e.g.
     * reacting to a compromise - would otherwise have every token it signs
     * with the new key rejected here for up to an hour.
     *
     * @return array<string, mixed>
     */
    private function verifySignedJwt(string $jwt, RealmIdentityProvider $provider, array $discovery): array
    {
        if (empty($discovery['jwks_uri'])) {
            throw new RuntimeException('The identity provider does not advertise a JWKS endpoint.');
        }

        foreach ([false, true] as $forceRefresh) {
            try {
                $keys = JWK::parseKeySet($this->fetchJwks($provider->issuer, $discovery['jwks_uri'], $forceRefresh));

                return json_decode(json_encode(JWT::decode($jwt, $keys)), true);
            } catch (Throwable) {
                continue;
            }
        }

        throw new RuntimeException('The token signature could not be verified.');
    }

    /** @return array<string, mixed> */
    private function fetchJwks(string $issuer, string $jwksUri, bool $forceRefresh): array
    {
        $cacheKey = 'identity-provider-jwks:'.md5($issuer);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHour(), fn (): array => Http::get($jwksUri)->throw()->json());
    }

    /**
     * Records which StuMV session a login via this provider/sub established,
     * so a later logout_token for that sub can find and end it. Skipped
     * silently if the provider didn't return a sub - back-channel logout
     * simply won't be able to reach that session, everything else about the
     * login still works. Keyed on session_id (unique in the schema) via
     * updateOrCreate rather than create(), since an already-authenticated
     * user re-running this flow to pick up a new mapping (see callback()'s
     * doc comment) keeps their existing session_id - a plain create() would
     * hit that unique constraint on every such re-sync.
     */
    private function rememberSession(RealmIdentityProvider $provider, array $claims, string $sessionId): void
    {
        if (empty($claims['sub'])) {
            return;
        }

        IdentityProviderSession::updateOrCreate(
            ['session_id' => $sessionId],
            ['provider_id' => $provider->id, 'external_sub' => $claims['sub']]
        );
    }

    private function authorizeProvider(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_unless($provider->enabled && $provider->realm === $realm->getShortCode(), 404);
    }

    private function buildProvider(Community $realm, RealmIdentityProvider $provider, array $discovery): GenericProvider
    {
        return $this->providerFactory->make([
            'clientId' => $provider->client_id,
            'clientSecret' => $provider->client_secret,
            'redirectUri' => route('identity-provider.callback', ['realm' => $realm->getShortCode(), 'provider' => $provider->id]),
            'urlAuthorize' => $discovery['authorization_endpoint'],
            'urlAccessToken' => $discovery['token_endpoint'],
            'urlResourceOwnerDetails' => $discovery['userinfo_endpoint'],
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ]);
    }

    private function discover(string $issuer): array
    {
        return Cache::remember('identity-provider-discovery:'.md5($issuer), now()->addHour(), fn (): array => Http::get(rtrim($issuer, '/').'/.well-known/openid-configuration')->throw()->json());
    }
}
