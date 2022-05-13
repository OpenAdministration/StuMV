<?php

namespace app\models\forms;

use app\models\validators\MailDomainRegistrationValidator;
use yii\helpers\Url;

class MailInviteForm extends \yii\base\Model
{
    public $mails;
    /**
     * @var array generated from $mails by exploding by newlines
     */
    public $mailsSplit;

    public $defaultRole;

    public $defaultRealm;

    public function rules() : array
    {
        return [
            [['mails', 'defaultRealm', 'defaultRole'], 'safe'],
            ['mailsSplit', 'each', 'rule' => [MailDomainRegistrationValidator::class], 'stopOnFirstError' => false,]
        ];
    }

    public function attributeLabels() : array
    {
        return [
            'mails' => 'Nutzer*innen Adressen',
            'defaultRole' => 'Start Rolle',
            'defaultRealm' => 'Start Realm',
        ];
    }

    public function beforeValidate(): bool
    {
        $this->mailsSplit = explode(PHP_EOL, $this->mails);
        $this->mailsSplit = array_map(static fn($item) => trim($item), $this->mailsSplit);
        return true;
    }

    public function afterValidate()
    {
        if($this->hasErrors('mailsSplit')){
            $this->addErrors(['mails' => $this->getErrors('mailsSplit')]);
        }
        return true;
    }

    /**
     * @param bool $runValidation
     * @return bool whether the saving succeeded (i.e. no validation errors occurred).
     */
    public function save(bool $runValidation = true) : bool
    {
        if($runValidation){
            $val =  $this->validate();
            if($val === false){
                return false;
            }
        }
        $messages = [];
        $invUser = \Yii::$app->getUser()->getIdentity();
        $successFullSentMails = 0;
        foreach ($this->mailsSplit as $mail){
            $sentSuccessfull = \Yii::$app->getMailer()
                ->compose('invite', [
                    'invitedByFullName' => $invUser->fullName,
                    'invitedByMail' => $invUser->email,
                    'registrationUrl' => Url::to(['auth/register'], true),
                ])->setTo($mail)
                ->send();
            if(!$sentSuccessfull){
                \Yii::$app->getSession()->addFlash('error', "Versand an {$mail} fehlgeschlagen");
            }else{
                $successFullSentMails++;
            }
        }
        \Yii::$app->getSession()->addFlash('success', "{$successFullSentMails} Mail(s) erfolgreich versendet");
        return $successFullSentMails === count($this->mailsSplit);
    }

    public function attributeHints() : array
    {
        return [
            'mails' => htmlentities('Nur eine Email pro Zeile, im Format "mail@example.com"')
        ];
    }
}