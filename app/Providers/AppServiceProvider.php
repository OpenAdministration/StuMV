<?php

namespace App\Providers;

use App\Auth\Passwords\RealmScopedPasswordBrokerManager;
use App\Http\Middleware\SetContentSecurityPolicy;
use App\Services\Oidc\LoggingClientRepository;
use App\Services\Oidc\ReuseDetectingRefreshTokenRepository;
use App\Support\MailmanClient;
use App\Support\RealmContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\AccessTokenRevoked;
use Laravel\Passport\Events\RefreshTokenCreated;
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

        // Logs *why* an OIDC client failed to authenticate at the token
        // endpoint - League OAuth2 Server's own invalid_client response is a
        // deliberate, well-formed OAuth error, never an uncaught exception,
        // so it otherwise never reaches storage/logs/laravel.log at all.
        $this->app->bind(ClientRepository::class, LoggingClientRepository::class);

        // See App\Services\Oidc\ReuseDetectingRefreshTokenRepository - a
        // replayed (already-rotated) refresh token revokes every token for
        // that user/client instead of just failing the one request.
        $this->app->bind(RefreshTokenRepository::class, ReuseDetectingRefreshTokenRepository::class);

        $this->app->singleton(MailmanClient::class, fn (): MailmanClient => new MailmanClient(
            (string) config('services.mailman.url'),
            (string) config('services.mailman.api_user'),
            (string) config('services.mailman.api_key'),
        ));

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

        // Neither was ever set, which silently left Passport on its own
        // one-year default for both - a stolen access token would stay
        // usable for a year even though refreshing is cheap and automatic
        // for any real client.
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

        // App\Services\Oidc\ReuseDetectingRefreshTokenRepository's reuse
        // detection only means anything if a used refresh token actually
        // gets revoked - otherwise a stolen token could be replayed
        // indefinitely instead of tripping the family-revoke on its second
        // use. This is already Passport's own default, but a security
        // invariant this load-bearing shouldn't depend on an undocumented
        // vendor default silently changing under us.
        Passport::$revokeRefreshTokenAfterUse = true;

        // Token issuance/revocation otherwise leaves no trace at all -
        // these are Passport's own events (Bridge\AccessTokenRepository,
        // Bridge\RefreshTokenRepository), not anything this app dispatches.
        Event::listen(function (AccessTokenCreated $event): void {
            Log::info('OIDC access token issued', ['client_id' => $event->clientId, 'user_id' => $event->userId]);
        });
        Event::listen(function (RefreshTokenCreated $event): void {
            Log::info('OIDC refresh token issued', ['access_token_id' => $event->accessTokenId]);
        });
        Event::listen(function (AccessTokenRevoked $event): void {
            Log::info('OIDC access token revoked', ['token_id' => $event->tokenId]);
        });

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

        // RequestHandled (not SetContentSecurityPolicy::handle() itself)
        // fires for every response, including one rendered by the exception
        // handler for a URL that matched no route at all - see
        // SetContentSecurityPolicy::apply()'s doc comment for why that case
        // can't be covered from inside the middleware. apply() itself checks
        // config('app.csp_enabled') (CSP_ENABLED in .env) first and no-ops
        // if it's off - checked here rather than by conditionally
        // registering this listener, so toggling it takes effect on the
        // next request, not just the next full restart.
        Event::listen(fn (RequestHandled $event) => SetContentSecurityPolicy::apply($event->response));

        if ($this->app->hasDebugModeEnabled()) {
            Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
                info("Missing translation key [$key] detected.");

                return $key;
            });
        }
    }
}
