<?php



/* @var $this View */
/* @var $model \app\models\db\User */


use app\models\db\User;
use app\widgets\Card;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\web\View;


Card::begin([
    'containerOptions' => ['class' => 'offset-lg-3 col-lg-6 mt-5'],
    'cardOptions' => ['class' => 'border border-secondary'],
    'headerOptions' => ['class' => 'bg-dark text-white'],
    'header' => 'Neue Benutzer:in anlegen',
]);

$form = ActiveForm::begin([
    'id' => 'register-form',
]);

echo $form->field($model, 'email');
echo $form->field($model, 'username');
echo $form->field($model, 'password')->passwordInput();
echo $form->field($model, 'password_repeat')->passwordInput();
echo Html::beginTag('div', ['class' => 'form-group']);
echo Html::submitButton('Registrieren', ['class' => 'btn btn-primary']);
echo Html::endTag('div');
ActiveForm::end();
Card::end();

