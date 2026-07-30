<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Ldap\Community;
use App\Models\RealmBranding;
use App\Models\RealmIdentityProvider;
use App\Providers\RouteServiceProvider;
use App\Support\EndsAuthenticatedSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

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

        // The OIDC `auth_time` claim / `max_age` request parameter (see
        // App\Http\Middleware\EnforceMaxAge, App\Services\Oidc\CustomAuthCodeGrant)
        // need to know when this session's actual authentication happened -
        // Laravel's own session store has no such marker (only a
        // continuously-refreshed "last activity" timestamp), so every login
        // path stamps this explicitly.
        $request->session()->put('auth_time', time());

        return redirect()->intended(RouteServiceProvider::home());
    }

    /**
     * Destroy an authenticated session.
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request, EndsAuthenticatedSession $endsSession, ?Community $realm = null)
    {
        $realmLoginUrl = $this->realmLoginUrl($realm, Auth::user());

        $endsSession->end($request);

        return redirect($request->input('redirect_uri', $realmLoginUrl));
    }

    public function confirmLogout(Request $request, ?Community $realm = null)
    {
        $user = Auth::user();

        return view('auth.logout-confirm', [
            'realm' => $realm,
            'branding' => RealmBranding::forRealm($realm?->getShortCode() ?? $user?->realm),
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
