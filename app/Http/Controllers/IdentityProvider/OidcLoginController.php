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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use SocialiteProviders\OpenIDConnect\Provider;

class OidcLoginController extends Controller
{
    public function __construct(private readonly OidcProviderFactory $providerFactory) {}

    /**
     * Send the visitor to the external identity provider's own login page.
     */
    public function redirect(Community $realm, RealmIdentityProvider $provider, Request $request): RedirectResponse
    {
        $this->authorizeProvider($realm, $provider);

        // Which of this realm's (possibly several) identity providers the
        // callback belongs to - orthogonal to Socialite's own `state` session
        // key, which only proves the redirect wasn't tampered with, not which
        // provider it was for.
        session(['identity_provider_id' => $provider->id]);

        return $this->buildProvider($realm, $provider, $request)->redirect();
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

        $validProviderForSession = session('identity_provider_id') === $provider->id;
        session()->forget('identity_provider_id');

        abort_unless($validProviderForSession, 400, 'Invalid or expired SSO login attempt.');

        // Declining the consent screen is a deliberate user action, not a
        // fault - send them back to the login page instead of an error page.
        if ($request->query('error') === 'access_denied') {
            return to_route('realm.login', ['realm' => $realm->getShortCode()])
                ->with('status', __('identity_providers.login_cancelled'));
        }

        try {
            $oidcUser = $this->buildProvider($realm, $provider, $request)->user();
        } catch (InvalidArgumentException $invalidArgumentException) {
            abort(400, $invalidArgumentException->getMessage());
        }

        // Trustworthy at this point: the id_token by signature, any merged-in
        // userinfo claims by the sub match the package itself already
        // enforces (OIDC Core 1.0 5.3.2) before returning them here.
        $claims = $oidcUser->getRaw();
        $email = $oidcUser->getEmail();

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

        $existing = User::where('email', $email)->where('realm', $realm->getShortCode())->first()
            ?? $this->userByAdditionalEmail($email, $realm);

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
            $claims = $this->buildProvider($realm, $provider, $request)->verifyLogoutToken($logoutToken);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return $this->backChannelLogoutError('invalid_request', $invalidArgumentException->getMessage());
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
     * Looks the account up by one of its additional email addresses, for when
     * the provider asserts an address that isn't anyone's primary. Those are
     * further values of the same LDAP "mail" attribute (see App\Ldap\User),
     * so one query covers them - the primary is only reached here if the
     * database lookup above already missed it, which means the account has no
     * database row yet.
     *
     * That case is rejected rather than falling through to the "create a new
     * account" path: the address demonstrably belongs to an existing LDAP
     * entry, and registering a second account for it would leave two entries
     * claiming the same address. It resolves itself as soon as the account
     * has signed in once with its password, which is what creates the row.
     */
    private function userByAdditionalEmail(string $email, Community $realm): ?User
    {
        $ldapUser = LdapUser::query()->in($realm->peopleDn())->where('mail', '=', $email)->first();

        if ($ldapUser === null) {
            return null;
        }

        $existing = User::where('uid', $ldapUser->getConvertedGuid())->first();

        abort_if($existing === null, 409, 'This email address belongs to an account that has not signed in yet. Please sign in with your password once first.');

        return $existing;
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

    private function buildProvider(Community $realm, RealmIdentityProvider $provider, Request $request): Provider
    {
        return $this->providerFactory->make($request, [
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect' => route('identity-provider.callback', ['realm' => $realm->getShortCode(), 'provider' => $provider->id]),
            'base_url' => rtrim($provider->issuer, '/'),
            'scopes' => $provider->scopes,
            'require_email' => true,
        ]);
    }
}
