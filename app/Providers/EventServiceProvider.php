<?php

namespace App\Providers;

use App\Listeners\RecordOidcClientConsent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Passport\Events\AccessTokenCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        AccessTokenCreated::class => [
            RecordOidcClientConsent::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    #[\Override]
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    #[\Override]
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
