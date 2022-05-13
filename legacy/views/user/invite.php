<?php

use yii\bootstrap4\ActiveForm;use yii\bootstrap4\Html;

/* @var $this \yii\web\View */
/* @var $model  */


$this->title = Yii::t('app', 'Einladen');
$this->params['breadcrumbs'][] = [
    'label' => 'Nutzer*innen',
    'url' => ['user/index'],
];
$this->params['breadcrumbs'][] = $this->title;


$form = ActiveForm::begin();

echo $form->field($model, 'mails')->textarea();

echo  Html::submitButton(\rmrevin\yii\fontawesome\FAS::icon('envelope-open-text') . ' ' . Yii::t('app', 'Einladungen versenden'), ['class' => 'btn btn-success']);

ActiveForm::end();


