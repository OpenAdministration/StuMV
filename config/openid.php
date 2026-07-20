<?php

use Lcobucci\JWT\Signer\Rsa\Sha256;
use OpenIDConnect\Repositories\IdentityRepository;

return [
    'passport' => [

        /**
         * Place your Passport and OpenID Connect scopes here.
         * To receive an `id_token, you should at least provide the openid scope.
         */
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect',
            'profile' => 'Information about your profile',
            'email' => 'Information about your email address',
            'phone' => 'Information about your phone numbers',
            'address' => 'Information about your address',
            'committees' => 'Information about your committees and roles',
            'groups' => 'Information about your groups',
            'users' => 'Information about users',
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
     * Optional associative array that will be used to set headers on the JWT
     */
    'token_headers' => [],

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
