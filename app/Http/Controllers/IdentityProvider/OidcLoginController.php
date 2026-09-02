<?php

namespace App\Http\Controllers\IdentityProvider;

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
use Firebase\JWT\Key;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\GenericProvider;
use RuntimeException;
use Throwable;

class OidcLoginController extends Controller
{
    /** Tolerated clock difference, in seconds, between this server and the provider. */
    private const int CLOCK_SKEW_LEEWAY = 60;

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
            'scope' => $this->scopesFor($provider),
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

        // Declining the consent screen is a deliberate user action, not a
        // fault - send them back to the login page instead of an error page.
        if ($request->query('error') === 'access_denied') {
            return to_route('realm.login', ['realm' => $realm->getShortCode()])
                ->with('status', __('identity_providers.login_cancelled'));
        }

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

        $userinfo = empty($discovery['userinfo_endpoint'])
            ? null
            : $oauthProvider->getResourceOwner($token)->toArray();

        // OIDC Core 1.0 5.3.2: the userinfo response's sub must match the
        // id_token's - otherwise a malicious/compromised userinfo endpoint
        // could hand back claims for a different subject than the one that
        // was actually authenticated.
        abort_unless(
            $userinfo === null || ($userinfo['sub'] ?? null) === $idTokenClaims['sub'],
            422,
            'The identity provider returned mismatched subject claims.'
        );

        // Both sources are trustworthy at this point (the id_token by
        // signature, userinfo by TLS + the sub check above), but they don't
        // carry the same claims: "groups" is commonly id_token-only (Entra ID
        // never returns it from userinfo at all, Keycloak's mapper has a
        // separate switch per token), while userinfo is the fresher of the
        // two, so it overlays rather than the other way round.
        $claims = array_merge($idTokenClaims, $userinfo ?? []);

        $email = $claims['email'] ?? null;
        abort_unless($email, 422, 'The identity provider did not return an email address.');

        // Accounts are matched by email below, so an address the provider
        // itself doesn't vouch for would let anyone able to set one at the
        // IdP claim an existing account. Only an explicit "false" counts: a
        // provider that omits the claim is taken at its word. Providers that
        // track no verification state and report every address as unverified
        // are handled by turning enforce_email_verified off for them.
        abort_if(
            $provider->enforce_email_verified
                && isset($claims['email_verified'])
                && ! filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN),
            422,
            'The identity provider reports this email address as unverified.'
        );

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

        $sessions = $this->sessionsToTerminate($provider, $claims);

        foreach ($sessions as $session) {
            resolve('session')->getHandler()->destroy($session->session_id);
        }

        IdentityProviderSession::whereKey($sessions->modelKeys())->delete();

        return response()->json([], 200)->header('Cache-Control', 'no-store');
    }

    /**
     * Back-Channel Logout 1.0 2.4 lets a logout_token identify what ended by
     * "sid", by "sub", or by both. sid is the precise one - it names the one
     * session that ended, where sub would also take down this user's other
     * sessions, which may well still be valid at the provider. So sid wins
     * wherever it's present, with sub left to cover only those sessions
     * recorded before the provider started sending one (external_sid null).
     *
     * @return Collection<int, IdentityProviderSession>
     */
    private function sessionsToTerminate(RealmIdentityProvider $provider, array $claims): Collection
    {
        if (! isset($claims['sid'])) {
            return IdentityProviderSession::where('provider_id', $provider->id)
                ->where('external_sub', $claims['sub'])
                ->get();
        }

        return IdentityProviderSession::where('provider_id', $provider->id)
            ->where(function ($query) use ($claims): void {
                $query->where('external_sid', $claims['sid']);

                if (isset($claims['sub'])) {
                    $query->orWhere(fn ($subQuery) => $subQuery
                        ->where('external_sub', $claims['sub'])
                        ->whereNull('external_sid'));
                }
            })
            ->get();
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

        if (($decoded['iss'] ?? null) !== $this->expectedIssuer($provider, $discovery)) {
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

        // Back-Channel Logout 1.0 2.4: either identifier on its own is a valid
        // way to say which session ended, but without one of them there's
        // nothing to match against.
        if (empty($decoded['sub']) && empty($decoded['sid'])) {
            throw new RuntimeException('logout_token is missing both the sub and sid claims.');
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

        if (($decoded['iss'] ?? null) !== $this->expectedIssuer($provider, $discovery)) {
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

        // RFC 7517 §4.4 makes "alg" optional on a JWK, but the library
        // requires one per key unless a default is given - authentik, among
        // others, publishes JWKS entries without it.
        $defaultAlg = $discovery['id_token_signing_alg_values_supported'][0] ?? 'RS256';

        // php-jwt defaults to zero tolerance on iat/nbf/exp, so a server clock
        // a second or two ahead of the provider's rejects otherwise perfectly
        // good tokens.
        JWT::$leeway = self::CLOCK_SKEW_LEEWAY;

        $lastError = null;

        foreach ([false, true] as $forceRefresh) {
            try {
                $keys = JWK::parseKeySet($this->fetchJwks($provider->issuer, $discovery['jwks_uri'], $forceRefresh), $defaultAlg);

                return json_decode(json_encode(JWT::decode($jwt, $this->keyFor($jwt, $keys))), true);
            } catch (Throwable $throwable) {
                $lastError = $throwable;
            }
        }

        Log::warning('Failed to verify a JWT from an identity provider.', [
            'provider_id' => $provider->id,
            'issuer' => $provider->issuer,
            'error' => $lastError?->getMessage(),
        ]);

        throw new RuntimeException('The token signature could not be verified.');
    }

    /**
     * Picks the key to verify against. php-jwt insists on a "kid" header to
     * look one up in a key set, but that header is optional - a provider
     * publishing a single signing key has nothing to disambiguate and may
     * well leave it out, so resolve that case ourselves instead of failing.
     *
     * @param  array<string, Key>  $keys
     * @return array<string, Key>|Key
     */
    private function keyFor(string $jwt, array $keys): array|Key
    {
        if (count($keys) !== 1) {
            return $keys;
        }

        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]), true);

        return isset($header['kid']) ? $keys : reset($keys);
    }

    /**
     * The issuer an id_token/logout_token from this provider must name. OIDC
     * Discovery 1.0 3.3 makes the discovery document's own "issuer" the
     * authoritative spelling, and it isn't always the one an admin typed:
     * Auth0, for instance, issues tokens with a trailing slash that the
     * configured URL doesn't carry. Only taken over when it denotes the same
     * issuer, so a tampered-with discovery document can't redefine who we
     * trust; anything else falls back to the configured value.
     */
    private function expectedIssuer(RealmIdentityProvider $provider, array $discovery): string
    {
        $configured = rtrim($provider->issuer, '/');
        $advertised = $discovery['issuer'] ?? null;

        return is_string($advertised) && rtrim($advertised, '/') === $configured
            ? $advertised
            : $configured;
    }

    /**
     * The scopes to request, always including "openid" - without it the
     * provider runs a plain OAuth2 flow and returns no id_token at all,
     * which would strand the login in verifyIdToken().
     */
    private function scopesFor(RealmIdentityProvider $provider): string
    {
        $scopes = preg_split('/\s+/', (string) $provider->scopes, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_unique(['openid', ...$scopes]));
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
            [
                'provider_id' => $provider->id,
                'external_sub' => $claims['sub'],
                // Only some providers issue one; it lets a later logout_token
                // end exactly this session rather than all of the user's.
                'external_sid' => $claims['sid'] ?? null,
            ]
        );
    }

    private function authorizeProvider(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_unless($provider->enabled && $provider->realm === $realm->getShortCode(), 404);
    }

    private function buildProvider(Community $realm, RealmIdentityProvider $provider, array $discovery): GenericProvider
    {
        abort_if(
            empty($discovery['authorization_endpoint']) || empty($discovery['token_endpoint']),
            502,
            'The identity provider\'s discovery document is missing a required endpoint.'
        );

        return $this->providerFactory->make([
            'clientId' => $provider->client_id,
            'clientSecret' => $provider->client_secret,
            'redirectUri' => route('identity-provider.callback', ['realm' => $realm->getShortCode(), 'provider' => $provider->id]),
            'urlAuthorize' => $discovery['authorization_endpoint'],
            'urlAccessToken' => $discovery['token_endpoint'],
            // Required by GenericProvider even where the provider publishes no
            // userinfo endpoint; callback() skips the call in that case and
            // works off the id_token's claims alone.
            'urlResourceOwnerDetails' => $discovery['userinfo_endpoint'] ?? '',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ]);
    }

    private function discover(string $issuer): array
    {
        return Cache::remember('identity-provider-discovery:'.md5($issuer), now()->addHour(), fn (): array => Http::get(rtrim($issuer, '/').'/.well-known/openid-configuration')->throw()->json());
    }
}
