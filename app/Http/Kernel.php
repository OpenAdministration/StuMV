<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CommunityAdmin;
use App\Http\Middleware\CommunityMember;
use App\Http\Middleware\CommunityModerator;
use App\Http\Middleware\DenyAdminRealm;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureAccountIsNotLocked;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureOidcClientMatchesRealm;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetContentSecurityPolicy;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            SetContentSecurityPolicy::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            SetLocale::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            EnsureAccountIsNotLocked::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        // custom
        'superadmin' => SuperAdminMiddleware::class,
        'communityAdmin' => CommunityAdmin::class,
        'communityMod' => CommunityModerator::class,
        'communityMember' => CommunityMember::class,
        'denyAdminRealm' => DenyAdminRealm::class,
        'oidcClientMatchesRealm' => EnsureOidcClientMatchesRealm::class,
        'scopes' => CheckToken::class,
        'scope' => CheckTokenForAnyScope::class,
        // Rejects any token that has a human resource owner (i.e. a normal
        // delegated end-user login) - only genuine client-credentials
        // tokens (the client authenticating as itself) pass through.
        'client' => EnsureClientIsResourceOwner::class,
    ];
}
