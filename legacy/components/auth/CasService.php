<?php

/**
 * @license MIT License
 */

namespace app\components\auth;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use phpCAS;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

/**
 * Wrapper on phpCAS
 *
 * @author François Gannaz <francois.gannaz@silecs.info>
 * @author Lukas Staab
 *
 * @property-write bool $debug
 * @property-read null|array $attributes
 * @property-read string $username
 */
class CasService extends AuthService
{
    public const LOGPATH = '@runtime/logs/cas.log';

    public string $host;
    public int $port;
    public string $path;
    public string $casVersion;

    /**
     *
     * @var string|boolean If defined, local path to a SSL certificate file,
     *                     or false to disable the certificate validation.
     */
    public $certfile;

    /**
     * @throws InvalidConfigException
     */
    public function init() : void
    {
        if (!isset($this->host, $this->port, $this->path, $this->casVersion)) {
            throw new InvalidConfigException("Incomplete CAS config. Required: host, port, path.");
        }
        // Force a Yii session to open to prevent phpCas from doing it on its own
        Yii::$app->session->open();
        // Init the phpCAS singleton

        $logger = new Logger('yii-auth-cas');
        $logger->pushHandler(new StreamHandler(Yii::getAlias(self::LOGPATH)));

        phpCAS::setLogger($logger);
        phpCAS::client($this->casVersion, $this->host, (int) $this->port, $this->path);

        if (!empty($this->certfile)) {
            phpCAS::setCasServerCACert($this->certfile);
        } else {
            phpCAS::setNoCasServerValidation();
        }
    }

    /**
     * Try to authenticate the current user.
     *
     * @return boolean returns true or redirect
     */
    public function forceAuthentication() : bool
    {
        if(!$this->isAuthenticated()){
            phpCAS::setFixedServiceURL(Url::current([], true));
            return phpCAS::forceAuthentication();
        }
        return true;
    }

    /**
     * Check if the current user is already authenticated.
     *
     * @return boolean
     */
    public function isAuthenticated() : bool
    {
        return phpCAS::checkAuthentication();
    }

    /**
     * Logout on the CAS server. The user is then redirected to $url.
     *
     * @param string $url
     */
    public function logout(string $url): void
    {
        if ($this->isAuthenticated()) {
            phpCAS::logout(['service' => $url]);
        }
    }

    /**
     * Return the username if authenticated by CAS, else the empty string.
     *
     * @return string
     */
    public function getUsername(): string
    {
        $this->forceAuthentication();
        return phpCAS::getUser();
    }

    /**
     * Return the attributes if authenticated by CAS, else return null.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $this->forceAuthentication();
        return phpCAS::getAttributes();
    }

    /**
     * Toggle the CAS debug mode that will add more logs into self::LOGPATH.
     *
     * @param boolean $debug
     * @return $this
     */
    public function setDebug(bool $debug = true): CasService
    {
        phpCAS::setVerbose($debug);
        return $this;
    }

}
