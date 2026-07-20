<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\RealmBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return View
     */
    public function create(Community $realm)
    {
        return view('auth.forgot-password', [
            'realm' => $realm,
            'branding' => RealmBranding::forRealm($realm->getShortCode()),
        ]);
    }

    /**
     * Handle an incoming password reset link request, scoped to one realm.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Community $realm, Request $request)
    {
        $request->validate([
            'mail' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        // ScopedToRealmPeople (applied to the LDAP guard's provider) resolves
        // the realm from this request's {realm} route parameter, so the link
        // is only sent if an account with this email exists in this realm.
        $status = Password::sendResetLink(
            $request->only('mail')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('mail'))
                        ->withErrors(['mail' => __($status)]);
    }
}
