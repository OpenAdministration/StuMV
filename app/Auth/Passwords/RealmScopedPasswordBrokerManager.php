<?php

namespace App\Auth\Passwords;

use Illuminate\Auth\Passwords\PasswordBrokerManager;

/**
 * Always builds a RealmScopedTokenRepository instead of the stock
 * DatabaseTokenRepository - see that class for why plain email-keyed tokens
 * aren't safe once the same email can belong to accounts in more than one
 * realm. This app only ever configures the database driver (no
 * config('auth.passwords.*.driver') === 'cache' broker), so the cache-driver
 * branch upstream's own createTokenRepository() handles isn't reproduced here.
 */
class RealmScopedPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config)
    {
        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new RealmScopedTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
