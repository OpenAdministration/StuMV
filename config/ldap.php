<?php

$options = array_filter([
    LDAP_OPT_X_TLS_REQUIRE_CERT => env('LDAP_TLS_VERIFY', true) ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER,
    LDAP_OPT_X_TLS_CACERTFILE => env('LDAP_TLS_CACERT'),
    LDAP_OPT_PROTOCOL_VERSION => 3,
], static fn (mixed $value): bool => $value !== null && $value !== '');

return [

    /*
    |--------------------------------------------------------------------------
    | Default LDAP Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the LDAP connections below you wish
    | to use as your default connection for all LDAP operations. Of
    | course you may add as many connections you'd like below.
    |
    */

    'default' => env('LDAP_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Connections
    |--------------------------------------------------------------------------
    |
    | Below you may configure each LDAP connection your application requires
    | access to. Be sure to include a valid base DN - otherwise you may
    | not receive any results when performing LDAP search operations.
    |
    */

    'connections' => [
        'default' => [
            'hosts' => [env('LDAP_HOST', '127.0.0.1')],
            'username' => env('LDAP_USERNAME'),
            'password' => env('LDAP_PASSWORD'),
            'port' => env('LDAP_PORT', 389),
            'base_dn' => env('LDAP_BASE_DN'),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'use_tls' => false,
            'use_starttls' => true,
            'options' => $options,
        ],
        'uni' => [
            'hosts' => [env('UNI_LDAP_HOST', '127.0.0.1')],
            'username' => env('UNI_LDAP_USERNAME'),
            'password' => env('UNI_LDAP_PASSWORD'),
            'port' => env('UNI_LDAP_PORT', 389),
            'base_dn' => env('UNI_LDAP_BASE_DN'),
            'timeout' => env('UNI_LDAP_TIMEOUT', 5),
            'use_tls' => false,
            'use_starttls' => true,
            'options' => $options,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Logging
    |--------------------------------------------------------------------------
    |
    | When LDAP logging is enabled, all LDAP search and authentication
    | operations are logged using the default application logging
    | driver. This can assist in debugging issues and more.
    |
    */

    'logging' => [
        'enabled' => env('LDAP_LOGGING', true),
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Cache
    |--------------------------------------------------------------------------
    |
    | LDAP caching enables the ability of caching search results using the
    | query builder. This is great for running expensive operations that
    | may take many seconds to complete, such as a pagination request.
    |
    */

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver' => env('CACHE_DRIVER', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | University LDAP Search Batch Size
    |--------------------------------------------------------------------------
    |
    | The university LDAP server enforces a maximum number of results per
    | search request. Lookups against it are chunked into batches of this
    | size instead of a single unbounded query. This is a plain app-level
    | setting, not an LdapRecord connection option, so it lives here at the
    | top level rather than inside "connections.uni".
    |
    */

    'uni_batch_size' => env('UNI_LDAP_BATCH_SIZE', 10),

];
