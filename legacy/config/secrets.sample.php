<?php

// set to null if defaults should be used

return [
    'cookieValidationKey' => '<cookieValidationKey>',
    'db' => [
        'dsn' => '', // format: 'mysql:host=localhost;port=3307;dbname=testdb'
        'username' => '',
        'password' => '',
        'charset' => 'utf8',
    ],

    'cas' => [
        'host' => '',
        'port' =>  '', // default: '443'
        'path' => '', // default: '/idp/profile/cas',
        // optional parameters
        'certfile' => '', // default: ''  // empty string, or path to a SSL cert, or false to ignore certs
        'debug' => true, // default: false will add many logs into X/runtime/logs/cas.log
    ],

    'mail' => [
        'encryption' => 'tls',
        'host' => 'your_mail_server_host',
        'port' => 'your_smtp_port',
        'username' => 'your_username',
        'password' => 'your_password',
    ]
];
