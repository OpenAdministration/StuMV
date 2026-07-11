<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withCache(
        // specify a path that works locally as well as on CI job runners
        cacheDirectory: '/tmp/rector',

        // ensure file system caching is used instead of in-memory
        cacheClass: FileCacheStorage::class
    )
    ->withSets([
        // bug in AuthenticateSessionController.php -> replaces view('auth.login') with view(\Illuminate\Events\Login:class)'
        // @see https://github.com/driftingly/rector-laravel/issues/317
        // LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPhpSets() // defaults to the php version from composer
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withSkip([
        // compact() is idiomatic in Laravel controllers; the explicit-array rewrite is
        // style-only and has an undefined-variable edge case. Keep compact().
        // CompactToVariablesRector::class,

    ]);
