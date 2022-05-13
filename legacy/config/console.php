<?php

use yii\log\FileTarget;
use yii\caching\FileCache;
use yii\gii\Module;
use yii\rbac\DbManager;

if(!file_exists(__DIR__ . '/secrets.php')){
    die("config/secrets.php missing");
}
$secrets = include __DIR__ . "/secrets.php";

$config = [
    'id' => 'yii-app-console',
    'name' => 'Yii App',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@locale'   => '@app/locale',
    ],
    'components' => [
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => $secrets['db']['dsn'],
            'username' => $secrets['db']['username'],
            'password' => $secrets['db']['password'],
            'charset' => 'utf8',
            // Schema cache options (for production environment)
            //'enableSchemaCache' => true,
            //'schemaCacheDuration' => 60,
            //'schemaCache' => 'cache',
        ],
        'authManager' => [
            'class' => DbManager::class,
            // uncomment if you want to cache RBAC items hierarchy
            // 'cache' => 'cache',
        ],
    ],
    'params' => [],
    'controllerMap' => [
        /* @see https://www.yiiframework.com/doc/guide/2.0/en/db-migrations#namespaced-migrations */
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => [
                '@app/migrations', // disable non-namespaced migrations if app\migrations is listed below
                '@yii/rbac/migrations',
            ],
        ],
    ],
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => Module::class,
    ];
}

return $config;
