<?php



/* @var $this View */
/* @var $user \app\models\db\User */

use app\models\db\User;
use app\widgets\Card;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\web\View;

Card::begin([
    'containerOptions' => ['class' => 'offset-lg-3 col-lg-6 mt-5'],
    'cardOptions' => ['class' => 'border border-secondary'],
    'headerOptions' => ['class' => 'bg-dark text-white'],
    'header' => 'Benutzer:innenprofil',
]);

$form = ActiveForm::begin([
    'id' => 'profile-form',
]);

echo $form->field($user, 'email')->staticControl()->hint('');
echo $form->field($user, 'username')->staticControl()->hint('');
echo $form->field($user, 'fullName');
echo $form->field($user, 'phone')->textInput(['placeholder' => '+49 ...']);
echo $form->field($user, 'iban')->textInput(['placeholder' => 'DE...']);
echo $form->field($user, 'adresse')->textarea([
    'placeholder' => 'Musterstraße 5a' . PHP_EOL . '00000 Musterstadt',
    'rows' => 2,
    'style' => 'resize:none',
]);
echo $form->field($user, 'imageFile')->fileInput();

echo Html::beginTag('div', ['class' => 'form-group']);
echo Html::submitButton('Speichern', ['class' => 'btn btn-primary']);
echo Html::endTag('div');

ActiveForm::end();
Card::end();

