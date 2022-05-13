<?php

use app\widgets\Card;
use rmrevin\yii\fontawesome\FAS;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\web\View;

/* @var View $this */
/* @var array $authNames */


Card::begin([
    'containerOptions' => ['class' => 'offset-lg-3 col-lg-6 mt-5'],
    'cardOptions' => ['class' => 'border border-secondary'],
    'headerOptions' => ['class' => 'bg-dark text-white'],
    'header' => 'Authentifizierungsquelle auswählen',
]);

foreach ($authNames as $authName){
    $authNameView = mb_strtoupper($authName);
    echo Html::a(FAS::icon('user-lock') . " Mit $authNameView einloggen", ['auth/login', 'service' => $authName], ['class' => 'btn btn-primary']);
}

// echo Html::a(FAS::icon('user-add') . " neuen CAS Account registrieren", ['auth/wayfinder',], ['class' => 'btn btn-info']);


Card::end();