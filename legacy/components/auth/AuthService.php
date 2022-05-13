<?php


namespace app\components\auth;



/**
 *
 * @property-write bool $debug
 * @property-read array $attributes
 * @property-read string $username
 */
abstract class AuthService extends \yii\base\BaseObject
{
    /**
     * Forces Authentication
     * @return bool true on success, redirect otherwise
     */
    abstract public function forceAuthentication() : bool;

    /**
     * @return bool true if authenticated, false otherwise
     */
    abstract public function isAuthenticated() : bool;

    /**
     * Logs the user out at the AuthService provider
     * @param string $url redirects to this url after logout
     */
    abstract public function logout(string $url): void;

    /**
     * @return string returns username, redirects if not authenticated
     */
    abstract public function getUsername(): string;

    /**
     * @return array returns all attributes, redirects if not authenticated
     */
    abstract public function getAttributes(): array;

    /**
     * activate debug error log
     * @param bool $debug
     * @return AuthService
     */
    abstract public function setDebug(bool $debug = true): AuthService;

}