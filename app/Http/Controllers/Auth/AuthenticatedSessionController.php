<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return Application|RedirectResponse|Redirector
     */
    public function create()
    {
        if (! Auth::guest()) {
            return redirect(RouteServiceProvider::home());
        }

        return view(Login::class);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @return RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

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
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect($request->input('redirect_uri', '/'));
    }

    public function confirmLogout(Request $request)
    {
        $user = Auth::user();

        return view('auth.logout-confirm', [
            'redirect_uri' => $request->input('redirect_uri', '/'),
            'shown_username' => "$user?->full_name ($user?->username)",
        ]);
    }
}
