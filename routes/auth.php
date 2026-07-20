<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OidcLoginController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Auth\CompleteSsoRegistration;
use App\Livewire\Profile\ChangePassword;
use App\Livewire\RegisterUser;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    // Plain, realm-agnostic community picker - deliberately does no
    // username-based lookup (that would leak which realms a given username
    // exists in before the visitor has authenticated at all).
    Route::get('login', [AuthenticatedSessionController::class, 'pickRealm'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'selectRealm']);

    Route::get('{realm}/login', [AuthenticatedSessionController::class, 'create'])->name('realm.login');
    Route::post('{realm}/login', [AuthenticatedSessionController::class, 'store']);

    // No separate register picker - registration is into one specific realm
    // (resolved by the domain the visitor's email belongs to, see
    // DomainRegistrationRule), so the realm still has to be chosen up front,
    // but that's now the same picker used for login (the realm-specific
    // login page it lands on links onward to {realm}/register). The admin
    // realm is never offered there and denyAdminRealm blocks it outright if
    // {realm}/register is visited directly.
    Route::get('register', fn () => redirect()->route('login'))->name('register');

    Route::livewire('{realm}/register', RegisterUser::class)->name('realm.register')
        ->middleware('denyAdminRealm');

    // Realm-scoped like login/register: LDAP auth queries always go through
    // ScopedToRealmPeople, which resolves the realm from this {realm} route
    // parameter first - without it, the guard has nothing to scope the
    // lookup to and aborts.
    Route::get('{realm}/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('{realm}/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('{realm}/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('{realm}/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');

    // External OIDC ("Login with X") - redirect/callback are plain
    // controller actions (no session established yet on the way out, and the
    // way back in needs to read the "code"/"state" query params), while the
    // "pick a username" step for brand-new accounts is a Livewire component
    // like registration itself. The literal "register" route must be
    // registered before the "{provider}" wildcard below, or the wildcard
    // would greedily match "register" as a provider id first.
    Route::livewire('{realm}/login/sso/register', CompleteSsoRegistration::class)->name('sso.register');
    Route::get('{realm}/login/sso/{provider}', [OidcLoginController::class, 'redirect'])->name('sso.redirect');
    Route::get('{realm}/login/sso/{provider}/callback', [OidcLoginController::class, 'callback'])->name('sso.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', [EmailVerificationPromptController::class, '__invoke'])
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::any('{realm}/profile/{username}/password', ChangePassword::class)->name('password.change');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('logout', [AuthenticatedSessionController::class, 'confirmLogout'])->name('logout.confirm');
});
