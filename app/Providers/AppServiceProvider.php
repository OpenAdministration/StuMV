<?php

namespace App\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(static function () {
            return Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        Builder::macro('search', function ($field, $string){
            return $string ? $this->orWhere($field, 'like', '%' . $string . '%') : $this;
        });

        if($this->app->hasDebugModeEnabled()){
            Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
                info("Missing translation key [$key] detected.");
                return $key;
            });
        }
    }
}

