<?php

use Lcobucci\JWT\Signer\Rsa\Sha256;
use OpenIDConnect\Repositories\IdentityRepository;

return [
    'passport' => [

        /**
         * Place your Passport and OpenID Connect scopes here.
         * To receive an `id_token, you should at least provide the openid scope.
         *
         * These descriptions are only used internally by Passport (e.g. for
         * scope validation) - they are never shown to users. The consent
         * screen instead translates each scope key via 'auth.scope_<key>'
         * (see resources/views/auth/oauth/authorize.blade.php and
         * lang/*\/auth.php), so add a matching translation there for any
         * scope added here.
         */
        'tokens_can' => [
            'openid' => 'OpenID Connect',
            'profile' => 'Profile',
            'email' => 'Email address',
            'phone' => 'Phone number',
            'address' => 'Address',
            'committees' => 'Committees and roles',
            'groups' => 'Groups',
            'users' => 'Users',
            // 'login' => 'See your login information',
        ],
    ],

    /**
     * Place your custom claim sets here.
     */
    'custom_claim_sets' => [
        // 'login' => [
        //     'last-login',
        // ],
        // 'company' => [
        //     'company_name',
        //     'company_address',
        //     'company_phone',
        //     'company_email',
        // ],
        'groups' => [
            'groups',
        ],
    ],

    /**
     * You can override the repositories below.
     */
    'repositories' => [
        'identity' => IdentityRepository::class,
    ],

    'routes' => [
        /**
         * Global discovery/jwks/userinfo routes are disabled - OIDC clients
         * (and the endpoints they authenticate through) are realm-bound, so
         * routes/web.php registers realm-prefixed replacements instead:
         * {realm}/.well-known/openid-configuration (App\Http\Controllers\Oidc\RealmDiscoveryController),
         * {realm}/oauth/jwks and {realm}/oauth/userinfo (this package's own
         * JwksController/UserInfoController, reused as-is - neither depends
         * on the realm, they're just registered under its path prefix).
         */
        'discovery' => false,
        'jwks' => false,
        'jwks_url' => '/oauth/jwks',
        'userinfo' => false,
    ],

    /**
     * Settings for the discovery endpoint
     */
    'discovery' => [
        /**
         * Hide scopes that aren't from the OpenID Core spec from the Discovery,
         * default = false (all scopes are listed)
         */
        'hide_scopes' => false,
    ],

    /**
     * The signer to be used
     */
    'signer' => Sha256::class,

    /**
     * Optional associative array that will be used to set headers on the JWT.
     *
     * A stable 'kid' matters more than it looks: without one, id_tokens are
     * signed with no key identifier at all, and OpenIDConnect\Laravel\JwksController
     * only publishes a 'kid' on the JWKS key if this is set (see its `if
     * ($kid = config('openid.token_headers.kid', false))` check) - some
     * relying parties' JWT/JWKS libraries (e.g. Nextcloud user_oidc, built on
     * web-token/jwt-framework) require an exact 'kid' match to select a
     * signing key at all and fail outright ("unable to lookup correct key")
     * rather than falling back to "the only key in the set", even though
     * StuMV only ever publishes one. App\Services\Oidc\BackChannelLogoutTokenBuilder
     * reads this same value so a client's back-channel logout_token verifies
     * against the same kid its id_tokens do.
     */
    'token_headers' => ['kid' => 'stumv-oidc-1'],

    /**
     * By default, microseconds are included.
     */
    'use_microseconds' => true,

    /**
     * Value for the issuedBy params. By default: laravel to get the scheme and host from the $_SERVER variable.
     * Options: laravel (use Request to extract scheme and host), server (use $_SERVER to detect)
     * or another string that will be used as-is
     */
    'issuedBy' => 'laravel',

    /**
     * By default, https is enforce. You can disable it here.
     */
    'forceHttps' => true,
];
