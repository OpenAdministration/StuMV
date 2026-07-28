<?php

namespace App\Support;

use App\Jobs\Oidc\SendBackChannelLogoutNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token as PassportToken;

/**
 * Ends the current web session and tells every OIDC client the user holds a
 * live token for (and that registered a back_channel_logout_uri) that it
 * happened. Shared by App\Http\Controllers\Auth\AuthenticatedSessionController::destroy()
 * (the user's own "log out" action) and App\Http\Controllers\Oidc\EndSessionController
 * (an OIDC client redirecting the browser here per RP-Initiated Logout 1.0) -
 * both need the exact same session-teardown + notification behavior, just
 * triggered from different places.
 */
class EndsAuthenticatedSession
{
    public function end(Request $request): void
    {
        $this->notifyOidcClientsOfLogout(Auth::user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }

    /**
     * Queries Token directly rather than $user->tokens() - that helper's
     * getProviderName() only resolves providers configured with the
     * 'eloquent' driver, but this app's users provider uses 'ldap' (see
     * config/auth.php), so it always throws here.
     *
     * One notification per live token, not deduped per client: each token's
     * own id is the `sid` App\Services\Oidc\IdTokenResponse already put in
     * that session's id_token, so a user with more than one live token for
     * the same client (e.g. two devices) needs one logout_token per token,
     * each carrying its own sid - otherwise the client can't tell which
     * session actually ended.
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
            ->filter(fn (PassportToken $token) => filled($token->client?->back_channel_logout_uri))
            ->each(fn (PassportToken $token) => dispatch(new SendBackChannelLogoutNotification(
                $token->client,
                (string) $user->uid,
                $token->getKey(),
            )));
    }
}
