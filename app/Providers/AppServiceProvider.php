<?php

namespace App\Providers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Lang;
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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Merged so the OpenID Connect package's scopes (openid, email, phone -
        // config/openid.php) are available alongside StuMV's own; where both
        // define a scope (profile, address), StuMV's own description wins.
        Passport::tokensCan(array_merge(config('openid.passport.tokens_can'), [
            'profile' => 'Grant Profile Info Access',
            'committees' => 'Grant Committee Access',
            'groups' => 'Grant Group Access',
            'users' => 'Grant Directory User Info Access',
            'iban' => 'Grant IBAN Access',
            'address' => 'Grant Address Access',
        ]));

        Passport::setDefaultScope(['profile']);

        // Passport 13 ships no authorization/consent screen and does not bind
        // AuthorizationViewResponse by default, so register our own consent view.
        Passport::authorizationView('auth.oauth.authorize');

        // Laravel 13 dropped the automatic route('login') fallback in the
        // exception handler's unauthenticated() - a guest AuthenticationException
        // with no redirect target now yields an empty 401 instead of a login
        // redirect. Passport's authorize endpoint throws exactly that for guests
        // (GET /oauth/authorize carries only `web`, not `auth`), which broke SSO
        // login: visitors hit a bare 401 instead of the login form. Restore the
        // redirect so they log in and bounce back to the authorization request.
        AuthenticationException::redirectUsing(static fn (): string => route('login'));

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
