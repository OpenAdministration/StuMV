<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Jobs\Oidc\SendBackChannelLogoutNotification;
use App\Ldap\Community;
use App\Models\RealmBranding;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token as PassportToken;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the plain, realm-agnostic community picker (no username/lookup
     * involved - showing which realms exist is fine, showing which realm a
     * given username exists in would not be).
     *
     * @return Redirector|RedirectResponse|View
     */
    public function pickRealm()
    {
        if (! Auth::guest()) {
            return redirect(RouteServiceProvider::home());
        }

        return view('auth.login-pick-realm', [
            'realms' => Community::query()->get(),
        ]);
    }

    /**
     * Redirect to the chosen realm's own login form. Deliberately does not
     * touch credentials or LDAP at all here.
     */
    public function selectRealm(Request $request): RedirectResponse
    {
        $request->validate(['realm' => ['required', 'string']]);

        return to_route('realm.login', ['realm' => $request->string('realm')]);
    }

    /**
     * Display the login view for a specific realm.
     *
     * @return Redirector|RedirectResponse|View
     */
    public function create(Community $realm)
    {
        if (! Auth::guest()) {
            return redirect(RouteServiceProvider::home());
        }

        return view('auth.login', [
            'realm' => $realm,
            'branding' => RealmBranding::forRealm($realm->getShortCode()),
            'identityProviders' => RealmIdentityProvider::where('realm', $realm->getShortCode())->where('enabled', true)->get(),
        ]);
    }

    /**
     * Handle an incoming authentication request, scoped to one realm.
     *
     * @return RedirectResponse
     */
    public function store(Community $realm, LoginRequest $request)
    {
        $request->authenticate();

        // Re-stamp this account's realm on every successful login, rather
        // than relying on it having been set correctly elsewhere (e.g. by
        // registration) - it's always trivially known here (the {realm}
        // route parameter the credentials were just validated against), so
        // this keeps App\Models\User.realm self-healing.
        Auth::user()->update(['realm' => $realm->getShortCode()]);

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::home());
    }

    /**
     * Destroy an authenticated session.
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request, ?Community $realm = null)
    {
        $user = Auth::user();
        $realmLoginUrl = $this->realmLoginUrl($realm, $user);

        $this->notifyOidcClientsOfLogout($user);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect($request->input('redirect_uri', $realmLoginUrl));
    }

    /**
     * OIDC Back-Channel Logout: tell every client the user currently holds a
     * non-revoked token for - and that has registered a
     * back_channel_logout_uri - that this SSO session has ended, so it can
     * invalidate its own session for them. See
     * App\Services\Oidc\BackChannelLogoutTokenBuilder and
     * App\Jobs\Oidc\SendBackChannelLogoutNotification.
     *
     * Queries Token directly rather than $user->tokens() - that helper's
     * getProviderName() only resolves providers configured with the
     * 'eloquent' driver, but this app's users provider uses 'ldap' (see
     * config/auth.php), so it always throws here.
     */
    private function notifyOidcClientsOfLogout(?User $user): void
    {
        if (! $user) {
            return;
        }

        PassportToken::where('user_id', $user->getAuthIdentifier())
            ->where('revoked', false)
            ->with('client')
            ->get()
            ->pluck('client')
            ->filter(fn ($client) => filled($client?->back_channel_logout_uri))
            ->unique('id')
            ->each(fn ($client) => dispatch(new SendBackChannelLogoutNotification($client, (string) $user->getAuthIdentifier())));
    }

    public function confirmLogout(Request $request, ?Community $realm = null)
    {
        $user = Auth::user();

        return view('auth.logout-confirm', [
            'realm' => $realm,
            'redirect_uri' => $request->input('redirect_uri', $this->realmLoginUrl($realm, $user)),
            'shown_username' => "$user?->full_name ($user?->username)",
        ]);
    }

    /**
     * Where to send the user after logging out - the realm they were just
     * browsing (the {realm} this route was hit through, e.g. relevant for a
     * superadmin logging out of a different realm than their own), falling
     * back to their own account's realm for the rare realm-less logout route
     * (e.g. from /pick-realm). Falls back further still to the generic
     * picker if neither is known, or the realm no longer exists.
     */
    private function realmLoginUrl(?Community $realm, mixed $user): string
    {
        return Community::loginUrlFor($realm?->getShortCode() ?? $user?->realm);
    }
}
