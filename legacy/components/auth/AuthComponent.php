<?php


namespace app\components\auth;


use yii\base\Component;
use yii\helpers\ArrayHelper;

/**
 * @property-write array $config
 * @property AuthService $authService
 * @property-read array $possibleAuthWays
 */
class AuthComponent extends Component
{

    private array $_config;

    /**
     * @var AuthService $auth the active / picked auth Service, references one of the implementations above
     */
    private AuthService $_auth;

    public function init() : void
    {
        foreach ($this->_config as $name => $conf){
            $class = ArrayHelper::remove($conf, 'class');
            \Yii::$container->setSingleton($class, $conf); // introduce configuration to DI Container
            \Yii::$container->set($name, $class); // set shorter alias
        }
        parent::init();
    }

    public function getPossibleAuthWays(): array
    {
        return array_keys($this->_config);
    }

    public function setConfig(array $config) : void
    {
        $this->_config = $config;
    }

    public function setAuthService(string $serviceName) : void {
        /** @var AuthService $auth */
        $auth = \Yii::$container->get($serviceName);
        $this->_auth = $auth;
    }

    public function getAuthService() : ?AuthService
    {
        if(isset($this->_auth)){
            return $this->_auth;
        }
        if(!\Yii::$app->user->isGuest){
            $serviceName =\Yii::$app->session->get('auth-service');
            $this->setAuthService($serviceName);
            return $this->_auth;
        }
        return null;
    }
}