<?php

namespace App\Services\Oidc;

use Laravel\Passport\Passport;
use Lcobucci\JWT\Signer\Key\InMemory;
use RuntimeException;

/** Passport's key pair as lcobucci/jwt keys, for tokens StuMV signs itself. */
final class SigningKeyFile
{
    public static function private(): InMemory
    {
        return self::load('oauth-private.key');
    }

    public static function public(): InMemory
    {
        return self::load('oauth-public.key');
    }

    private static function load(string $file): InMemory
    {
        $path = Passport::keyPath($file);

        if ($path === '') {
            throw new RuntimeException("No path configured for the Passport key {$file}.");
        }

        return InMemory::file($path);
    }
}
