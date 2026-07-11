<?php

namespace App\Providers;

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
        Passport::tokensCan([
            'profile' => 'Grant Profile Info Access',
            'committees' => 'Grant Committee Access',
            'groups' => 'Grant Group Access',
            'iban' => 'Grant IBAN Access',
            'address' => 'Grant Address Access',
        ]);

        Passport::setDefaultScope(['profile']);

        // Passport 13 ships no authorization/consent screen and does not bind
        // AuthorizationViewResponse by default, so register our own consent view.
        Passport::authorizationView('auth.oauth.authorize');

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
