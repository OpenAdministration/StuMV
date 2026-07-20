<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Ldap\Community;
use App\Models\RealmBranding;
use App\Models\RealmSsoProvider;
use App\Providers\RouteServiceProvider;
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
     * @return Redirector|RedirectResponse|\Illuminate\Contracts\View\View
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

        return redirect()->route('realm.login', ['realm' => $request->string('realm')]);
    }

    /**
     * Display the login view for a specific realm.
     *
     * @return Redirector|RedirectResponse|\Illuminate\Contracts\View\View
     */
    public function create(Community $realm)
    {
        if (! Auth::guest()) {
            return redirect(RouteServiceProvider::home());
        }

        return view('auth.login', [
            'realm' => $realm,
            'branding' => RealmBranding::forRealm($realm->getShortCode()),
            'ssoProviders' => RealmSsoProvider::where('realm', $realm->getShortCode())->where('enabled', true)->get(),
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
    public function destroy(Request $request)
    {
        $realmLoginUrl = $this->realmLoginUrl(Auth::user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect($request->input('redirect_uri', $realmLoginUrl));
    }

    public function confirmLogout(Request $request)
    {
        $user = Auth::user();

        return view('auth.logout-confirm', [
            'redirect_uri' => $request->input('redirect_uri', $this->realmLoginUrl($user)),
            'shown_username' => "$user?->full_name ($user?->username)",
        ]);
    }

    /**
     * Where to send the user after logging out - their own realm's login
     * page rather than the generic picker, so they don't have to re-pick
     * their community. Falls back to the picker if the user has no realm on
     * record, or it no longer exists.
     */
    private function realmLoginUrl(mixed $user): string
    {
        if ($user?->realm && Community::findByUid($user->realm)) {
            return route('realm.login', ['realm' => $user->realm]);
        }

        return route('login');
    }
}
