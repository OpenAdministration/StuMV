<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\RealmBranding;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     *
     * @return View
     */
    public function create(Community $realm, Request $request)
    {
        return view('auth.reset-password', [
            'realm' => $realm,
            'branding' => RealmBranding::forRealm($realm->getShortCode()),
            'request' => $request,
        ]);
    }

    /**
     * Handle an incoming new password request, scoped to one realm.
     *
     * @return RedirectResponse
     *
     * @throws ValidationException
     */
    public function store(Community $realm, Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'mail' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise, we will parse the error and return the response.
        // ScopedToRealmPeople resolves the realm from this request's {realm}
        // route parameter, so $user below is always this realm's own account
        // even if another realm has an account sharing the same email.
        $status = Password::reset(
            $request->only('mail', 'uid', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request): void {
                $user->forceFill([
                    'remember_token' => Str::random(60),
                ])->save();
                $ldapUser = $user->ldap();
                $ldapUser->setAttribute('userPassword', '{ARGON2}'.password_hash($request->password, PASSWORD_ARGON2ID));
                $ldapUser->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? to_route('realm.login', ['realm' => $realm->getShortCode()])->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
