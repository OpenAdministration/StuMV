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

        $oauthProvider = $this->buildProvider($realm, $provider);

        $authorizationUrl = $oauthProvider->getAuthorizationUrl(['scope' => 'openid email profile']);

        session([
            'identity_provider_state' => $oauthProvider->getState(),
            'identity_provider_id' => $provider->id,
        ]);

        return redirect($authorizationUrl);
    }

    /**
     * Handle the identity provider's redirect back. Matches the returned
     * email against an existing account in this realm (and logs it in
     * directly - the external IdP already vouched for the address, so no
     * LDAP bind is needed here), or hands off to the "pick a username"
     * completion step for a brand-new account.
     */
    public function callback(Community $realm, RealmIdentityProvider $provider, Request $request)
    {
        $this->authorizeProvider($realm, $provider);

        $state = $request->query('state');
        $validState = $state
            && $state === session('identity_provider_state')
            && session('identity_provider_id') === $provider->id;

        session()->forget(['identity_provider_state', 'identity_provider_id']);

        abort_unless($validState, 400, 'Invalid or expired SSO login attempt.');
        abort_if($request->has('error'), 400, (string) $request->query('error_description', 'The identity provider reported an error.'));

        $oauthProvider = $this->buildProvider($realm, $provider);

        $token = $oauthProvider->getAccessToken('authorization_code', [
            'code' => $request->query('code'),
        ]);

        $claims = $oauthProvider->getResourceOwner($token)->toArray();

        $email = $claims['email'] ?? null;
        abort_unless($email, 422, 'The identity provider did not return an email address.');

        $existing = User::where('email', $email)->where('realm', $realm->getShortCode())->first();

        if ($existing) {
            abort_if(
                LdapUser::isLockedByUsername($existing->username, $realm->peopleDn()),
                403,
                'This account has been locked.'
            );

            Auth::login($existing);
            $request->session()->regenerate();
            $request->session()->put('auth_time', time());

            resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $existing->username, $claims);
            resolve(IdentityProviderGroupSync::class)->apply($provider, $existing->username, $claims);
            $this->rememberSession($provider, $claims, $request->session()->getId());

            return redirect()->intended(RouteServiceProvider::home($realm->getShortCode()));
        }

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
            app('session')->getHandler()->destroy($session->session_id);
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

        if (empty($discovery['jwks_uri'])) {
            throw new RuntimeException('The identity provider does not advertise a JWKS endpoint.');
        }

        $jwks = Cache::remember(
            'identity-provider-jwks:'.md5($provider->issuer),
            now()->addHour(),
            fn (): array => Http::get($discovery['jwks_uri'])->throw()->json()
        );

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = json_decode(json_encode(JWT::decode($logoutToken, $keys)), true);
        } catch (Throwable) {
            throw new RuntimeException('The logout_token signature could not be verified.');
        }

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
     * Records which StuMV session a login via this provider/sub established,
     * so a later logout_token for that sub can find and end it. Skipped
     * silently if the provider didn't return a sub - back-channel logout
     * simply won't be able to reach that session, everything else about the
     * login still works.
     */
    private function rememberSession(RealmIdentityProvider $provider, array $claims, string $sessionId): void
    {
        if (empty($claims['sub'])) {
            return;
        }

        IdentityProviderSession::create([
            'provider_id' => $provider->id,
            'external_sub' => $claims['sub'],
            'session_id' => $sessionId,
        ]);
    }

    private function authorizeProvider(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_unless($provider->enabled && $provider->realm === $realm->getShortCode(), 404);
    }

    private function buildProvider(Community $realm, RealmIdentityProvider $provider): GenericProvider
    {
        $discovery = $this->discover($provider->issuer);

        return $this->providerFactory->make([
            'clientId' => $provider->client_id,
            'clientSecret' => $provider->client_secret,
            'redirectUri' => route('identity-provider.callback', ['realm' => $realm->getShortCode(), 'provider' => $provider->id]),
            'urlAuthorize' => $discovery['authorization_endpoint'],
            'urlAccessToken' => $discovery['token_endpoint'],
            'urlResourceOwnerDetails' => $discovery['userinfo_endpoint'],
        ]);
    }

    private function discover(string $issuer): array
    {
        return Cache::remember('identity-provider-discovery:'.md5($issuer), now()->addHour(), fn (): array => Http::get(rtrim($issuer, '/').'/.well-known/openid-configuration')->throw()->json());
    }
}
