<?php


namespace app\models\validators;


use app\models\db\Domain;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\validators\EmailValidator;
use yii\validators\Validator;

class MailDomainRegistrationValidator extends EmailValidator
{
    /**
     * @var string[]
     */
    private array $validDomainsForRegistration;



    /**
     * @inheritDoc
     */
    public function init() : void
    {
        parent::init();
        $this->validDomainsForRegistration = Domain::find()
            ->select('name')
            ->where(['forRegistration' => 1])
            ->asArray()->column();
    }

    /**
     * @inheritDoc
     */
    public function validateAttribute($model, $attribute) : void
    {
        parent::validateAttribute($model, $attribute);
    }

    /**
     * @inheritDoc
     */
    public function validateValue($value) : array
    {
        if(is_array($value)){
            $ret = [];
            foreach ($value as $v){
                $ret[] = $this->validate($v);
            }
            return $ret;
        }
        $validEmail = parent::validateValue($value);
        if(!empty($validEmail)){
            return ['{value} ist keine gültige Email Adresse', []];
        }

        $split = StringHelper::explode($value, '@');
        [,$domain] = $split;
        if(!ArrayHelper::isIn($domain, $this->validDomainsForRegistration)){
            return ['Die Domain "@{domain}" is nicht freigeschaltet, bitte verwende eine andere Mail-Adresse', ['domain' => $domain]];
        }
        return [];
    }

    /**
     * @inheritDoc
     */
    public function clientValidateAttribute($model, $attribute, $view) : string
    {
        /** Within the JavaScript code, you may use the following predefined variables:
         * - attribute: the name of the attribute being validated.
         * - value: the value being validated.
         * - messages: an array used to hold the validation error messages for the attribute.
         * - deferred: an array which deferred objects can be pushed into (explained in the next subsection).
         */
        $domains = json_encode($this->validDomainsForRegistration, JSON_THROW_ON_ERROR);
        $message = json_encode($this->message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return <<<JS
var domain = value.split('@');
if ($.inArray(domain[1], $domains) === -1) {
    messages.push($message);
}
JS;

    }

    /**
     * @return Domain[]
     */
    public function getValidDomains() : array
    {
        return $this->validDomainsForRegistration;
    }
}