<?php

namespace App\Providers;

use App\Ldap\Community;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    #[\Override]
    public function boot()
    {
        $this->configureRateLimiting();

        Route::bind('realm', fn (string $value) => Community::findByOrFail('ou', $value));
        // $this->model('uid', Community::class);

        $this->routes(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->prefix('api-legacy')
                ->group(base_path('routes/api-legacy.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Introspection and revocation authenticate a *client*, not a user -
        // keyed by client_id (falling back to IP only if it's missing
        // entirely) so one resource server's traffic can't exhaust another's
        // allowance just for sharing a NAT/gateway, and so this can't be used
        // as an unlimited oracle for guessing a client_secret or probing
        // whether a given token string is currently valid.
        RateLimiter::for('oidc-client', fn (Request $request) => Limit::perMinute(60)->by(
            (string) ($request->input('client_id') ?? $request->getUser() ?? $request->ip())
        ));
    }

    public static function home($uid = null)
    {
        if (! empty($uid)) {
            return route('realms.dashboard', $uid);
        }

        // Login is realm-scoped now, so an account has at most one realm by
        // construction - no membership counting needed, just trust the
        // "realm" column that was (re-)stamped on this account at login time
        // (see AuthenticatedSessionController::store()). Not every
        // authenticated App\Models\User has a matching LDAP entry yet (e.g.
        // mid-registration/email-verification) - ldapOrNull() (unlike
        // ->ldap()) returns null instead of aborting for those.
        $ldapUser = Auth::user()->ldapOrNull();

        if ($ldapUser && $ldapUser->isSuperAdmin()) {
            return route('realms.pick');
        }

        return Auth::user()->realm
            ? route('realms.dashboard', Auth::user()->realm)
            : route('realms.pick');
    }
}
