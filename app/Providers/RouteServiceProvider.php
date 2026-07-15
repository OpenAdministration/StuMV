<?php

namespace App\Providers;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
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
    }

    public static function home($uid = null)
    {
        if (empty($uid)) {
            // Skip the /pick-realm Livewire component boot for the common
            // case of a single-community member - go straight to their
            // dashboard in one redirect instead of two. Not every
            // authenticated App\Models\User has a matching LDAP entry (e.g.
            // mid-registration/email-verification) - findByUsername()
            // (unlike ->ldap()) returns null instead of aborting for those.
            $ldapUser = LdapUser::findByUsername(Auth::user()->username);

            if ($ldapUser && ! $ldapUser->isSuperAdmin()) {
                $memberships = Community::membershipsFor($ldapUser);

                if (count($memberships) === 1) {
                    return route('realms.dashboard', array_key_first($memberships));
                }
            }

            return route('realms.pick');
        }

        return route('realms.dashboard', $uid);
    }
}
