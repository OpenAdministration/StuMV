<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\RealmBranding;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     *
     * @return mixed
     */
    public function __invoke(Community $realm, Request $request)
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(RouteServiceProvider::home($realm->getShortCode()))
                    : view('auth.verify-email', [
                        'realm' => $realm,
                        'branding' => RealmBranding::forRealm($realm->getShortCode()),
                    ]);
    }
}
