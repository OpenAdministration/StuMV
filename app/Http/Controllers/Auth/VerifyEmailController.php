<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified - signed in or not, since
     * the link is usually opened right after registering, before any login.
     */
    public function __invoke(Community $realm, string $id, string $hash): RedirectResponse
    {
        $user = User::where('realm', $realm->getShortCode())->find($id) ?? abort(404);
        abort_unless(hash_equals(sha1((string) $user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (Auth::id() === $user->id) {
            return redirect()->intended(RouteServiceProvider::home($realm->getShortCode()).'?verified=1');
        }

        return to_route('realm.login', ['realm' => $realm->getShortCode()])
            ->with('status', __('user.email_verified_login'));
    }
}
