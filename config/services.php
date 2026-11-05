<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mailman' => [
        // Mailman 3 Core's own REST API root, e.g. http://localhost:8001/3.1
        // - not the public web UI (Postorius/Hyperkitty).
        'api_url' => env('MAILMAN_API_URL'),
        'api_user' => env('MAILMAN_API_USER', 'restadmin'),
        'api_key' => env('MAILMAN_API_KEY'),
        // true, false, or a path to a CA bundle to verify against.
        'api_verify_tls' => env('MAILMAN_API_VERIFY_TLS', true),
        // Optional mailman-web root (Postorius), e.g. https://lists.example.org.
        // If set, (un)subscribes go through its forms as this list owner.
        'web_url' => env('MAILMAN_WEB_URL'),
        'web_user' => env('MAILMAN_WEB_USER'),
        'web_password' => env('MAILMAN_WEB_PASSWORD'),
        'web_verify_tls' => env('MAILMAN_WEB_VERIFY_TLS', true),
    ],

];
