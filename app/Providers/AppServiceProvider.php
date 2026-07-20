<?php

namespace App\Providers;

use App\Auth\Passwords\RealmScopedPasswordBrokerManager;
use App\Support\RealmContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    #[\Override]
    public function register()
    {
        $this->app->singleton(RealmContext::class);

        // 'auth.password' is a deferred service (PasswordResetServiceProvider) -
        // container::extend() is the only override style that survives being
        // registered before the deferred provider's own singleton() binding
        // actually runs, since extenders apply to whatever gets built,
        // whenever that happens to be.
        $this->app->extend('auth.password', fn ($manager, $app) => new RealmScopedPasswordBrokerManager($app));

        // OIDC clients (and their /oauth/* endpoints) are realm-bound now -
        // the package's global routes are replaced with realm-prefixed ones
        // registered in routes/web.php, reusing the same vendor controllers.
        // Must happen in register() (runs for every provider before any
        // provider's boot()), since OpenIDConnect\Laravel\PassportServiceProvider
        // - registered before this one - checks this flag in its own boot().
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config/openid.php's tokens_can is the single source of truth for
        // scopes: the OpenID Connect package's discovery endpoint reads it
        // directly for scopes_supported, so any scope granted via Passport
        // must live there to be advertised at /.well-known/openid-configuration.
        Passport::tokensCan(config('openid.passport.tokens_can'));

        Passport::setDefaultScope(['profile']);

        // Passport 13 ships no authorization/consent screen and does not bind
        // AuthorizationViewResponse by default, so register our own consent view.
        Passport::authorizationView('auth.oauth.authorize');

        // Laravel 13 dropped the automatic route('login') fallback in the
        // exception handler's unauthenticated() - a guest AuthenticationException
        // with no redirect target now yields an empty 401 instead of a login
        // redirect. Passport's authorize endpoint throws exactly that for guests
        // (GET {realm}/oauth/authorize carries only `web`, not `auth`), which
        // broke SSO login: visitors hit a bare 401 instead of the login form.
        // Restore the redirect so they log in and bounce back to the
        // authorization request - straight to that realm's own login screen
        // when the request was already realm-scoped, the plain picker otherwise.
        AuthenticationException::redirectUsing(static function ($request): string {
            $realm = $request->route('realm');

            // Community isn't Illuminate\Contracts\Routing\UrlRoutable -
            // passing the model itself to route() falls back to its
            // __toString(), which returns the full DN, not the short code
            // the {realm} segment actually expects.
            return $realm ? route('realm.login', ['realm' => $realm->getShortCode()]) : route('login');
        });

        // verification.verify is realm-scoped ({realm}/verify-email/{id}/{hash})
        // like every other auth route - the stock signed-URL builder only
        // knows about id/hash, so it has to be told about the extra segment
        // here or the signature it produces won't match the route at all.
        VerifyEmail::createUrlUsing(static fn ($notifiable) => URL::temporarySignedRoute(
            'verification.verify',
            Date::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'realm' => $notifiable->realm,
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        ));

        Password::defaults(static fn () => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        Builder::macro('search', fn ($field, $string) => $string ? $this->orWhere($field, 'like', '%'.$string.'%') : $this);

        if ($this->app->hasDebugModeEnabled()) {
            Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
                info("Missing translation key [$key] detected.");

                return $key;
            });
        }
    }
}
