<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     *
     * @return RedirectResponse
     */
    public function store(Community $realm, Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::home($realm->getShortCode()));
        }

        // ->afterResponse(): neither the notification nor any listener here
        // is queued, so without this, the redirect would wait on a real SMTP
        // round-trip (same reasoning as App\Support\EndsAuthenticatedSession
        // and App\Livewire\RegisterUser).
        $user = $request->user();
        dispatch(function () use ($user): void {
            $user->sendEmailVerificationNotification();
        })->afterResponse();

        return back()->with('status', 'verification-link-sent');
    }
}
