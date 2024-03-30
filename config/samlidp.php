<?php

foreach (explode(',', env('SAML_COMMUNITIES')) as $community){
    $domain = env('SAML_SP_DOMAIN_' . $community);
    $secure = !str_contains($domain, 'localhost') && !str_contains($domain, '127.0.0.1');
    $protocol = "http" . ($secure? 's': '') . "://";
    $port = str_contains($domain, ':') ? "" : ( $secure ? ":443" : ":80");
    //$entityId = "https://" . $domain . "/auth/metadata";
    $SPs_config[base64_encode("$protocol$domain$port/auth/login")] = [
        'destination' => $protocol . $domain . "/auth/login",
        'logout' => $protocol . $domain . '/auth/logout',
        'certificate' => env('SAML_SP_CERT_' . $community, ''),
        'query_params' => false,
    ];
}

return [
    /*
    |--------------------------------------------------------------------------
    | SAML idP configuration file
    |--------------------------------------------------------------------------
    |
    | Use this file to configure the service providers you want to use.
    |
     */
    // Outputs data to your laravel.log file for debugging
    'debug' => env('APP_AUTH_DEBUG', env('APP_DEBUG')),
    // Define the email address field name in the users table
    'email_field' => 'email',
    // Define the name field in the users table
    'name_field' => 'full_name',
    // The URI to your login page
    'login_uri' => 'login',
    // Log out of the IdP after SLO
    'logout_after_slo' => env('LOGOUT_AFTER_SLO', false),
    // The URI to the saml metadata file, this describes your idP
    'issuer_uri' => 'saml/metadata',
    // The certificate
    'cert' => env('SAMLIDP_CERT'),
    // Name of the certificate PEM file, ignored if cert is used
    'certname' => 'cert.pem',
    // The certificate key
    'key' => env('SAMLIDP_KEY'),
    // Name of the certificate key PEM file, ignored if key is used
    'keyname' => 'key.pem',
    // Encrypt requests and responses
    'encrypt_assertion' => true,
    // Make sure messages are signed
    'messages_signed' => true,
    // Defind what digital algorithm you want to use
    'digest_algorithm' => \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA512,
    // list of all service providers
    'sp' => $SPs_config,

    // Base64 encoded ACS URL
    // 'aHR0cHM6Ly9teWZhY2Vib29rd29ya3BsYWNlLmZhY2Vib29rLmNvbS93b3JrL3NhbWwucGhw' => [
    //     // Your destination is the ACS URL of the Service Provider
    //     'destination' => 'https://myfacebookworkplace.facebook.com/work/saml.php',
    //     'logout' => 'https://myfacebookworkplace.facebook.com/work/sls.php',
    //    // SP certificate
    //     'certificate' => '',
    //    // Turn off auto appending of the idp query param
    //     'query_params' => false,
    //    // Turn off the encryption of the assertion per SP
    //     'encrypt_assertion' => false
    // ]

    // If you need to redirect after SLO depending on SLO initiator
    // key is beginning of HTTP_REFERER value from SERVER, value is redirect path
    'sp_slo_redirects' => [
        // 'https://example.com' => 'https://example.com',
    ],

    // All of the Laravel SAML IdP event / listener mappings.
    'events' => [
        CodeGreenCreative\SamlIdp\Events\Assertion::class => [],
        #Illuminate\Auth\Events\Logout::class => [CodeGreenCreative\SamlIdp\Listeners\SamlLogout::class],
        Illuminate\Auth\Events\Authenticated::class => [CodeGreenCreative\SamlIdp\Listeners\SamlAuthenticated::class],
        Illuminate\Auth\Events\Login::class => [CodeGreenCreative\SamlIdp\Listeners\SamlLogin::class],
    ],

    // List of guards saml idp will catch Authenticated, Login and Logout events
    'guards' => ['web'],
];
