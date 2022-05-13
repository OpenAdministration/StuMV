<?php

use app\models\db\User;
use yii\caching\FileCache;
use yii\log\FileTarget;
use yii\rbac\DbManager;

if(!file_exists(__DIR__ . '/secrets.php')){
    die("config/secrets.php missing");
}
$secrets = include __DIR__ . "/secrets.php";

$config = [
    'id' => 'yii-membership-management',
    'name' => 'Mitgliederverwaltung',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language' => 'de-DE',

    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@locale'   => '@app/locale',
        '@mail' => '@app/mail',
    ],

    'components' => [

        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => $secrets['db']['dsn'],
            'username' => $secrets['db']['username'],
            'password' => $secrets['db']['password'],
            'charset' => 'utf8',
            // Schema cache options (for production environment)
            'enableSchemaCache' => true,
            'schemaCacheDuration' => 60,
            'schemaCache' => 'cache',
        ],

        'request' => [
            'cookieValidationKey' => $secrets['cookieValidationKey'] ?? '',
            'baseUrl' => '/management'
        ],

        'cache' => [
            'class' => FileCache::class,
        ],

        'user' => [
            'class' => yii\web\User::class,
            'identityClass' => User::class,
            'loginUrl' => ['auth/wayfinder'],
            'enableAutoLogin' => true,
         ],

        'errorHandler' => [
            'errorAction' => 'site/error'
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => YII_ENV_DEV,
            'messageConfig' => [
                'from' => 'service@open-administration.de',
            ],
            'transport' => $secrets['mail'] ?? [],
        ],

        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],

        'authServices' => [
            'class' => \app\components\auth\AuthComponent::class,
            'config' => [
                'cas' => [
                    'class' => \app\components\auth\CasService::class,
                    'host' => $secrets['cas']['host'],
                    'port' => $secrets['cas']['port'] ?? '443',
                    'path' => $secrets['cas']['path'] ?? '/idp/profile/cas',
                    'casVersion' => '3.0',
                    // optional parameters
                    'certfile' => $secrets['cas']['certfile'] ?? false, // , or path to a SSL cert, or false to ignore certs
                    'debug' => $secrets['cas']['debug'] ?? false, // will add many logs into @runtime/logs/cas.log
                ],
            ],
        ],

        'authManager' => [
            'class' => DbManager::class,
            // uncomment if you want to cache RBAC items hierarchy
            'cache' => 'cache',
        ],

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                //enter routing rules here
                //'migrate/<action:\w*>' => 'migrate/catch-all',
            ],
        ],

        'i18n' => [
            'translations' => [
                '*' => [
                    'class' => yii\i18n\PhpMessageSource::class
                ],
            ],
        ],

        'view' => [
            'class' => \daxslab\taggedview\View::class,
            // @see https://github.com/daxslab/yii2-taggedview
            //configure some default values that will be shared by all the pages of the website
            //if they are not overwritten by the page itself
            //'image' => 'http://domain.com/images/default-image.jpg',
        ],
    ],

    'modules' => [

    ],

    'params' => [],
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
