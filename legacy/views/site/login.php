<?php

/* @var $this View */
/* @var $model LoginForm */

use app\models\db\site\LoginForm;
use app\widgets\Card;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\web\View;

Card::begin([
    'containerOptions' => ['class' => 'offset-lg-3 col-lg-6 mt-5'],
    'cardOptions' => ['class' => 'border border-secondary'],
    'headerOptions' => ['class' => 'bg-dark text-white'],
    'header' => 'Login',
]);
    $form = ActiveForm::begin() ?>
        <?= $form->field($model, 'username')->textInput() ?>
        <?= $form->field($model, 'password')->passwordInput() ?>
        <?= $form->field($model, 'rememberMe')->checkbox() ?>
        <div class="form-group">
            <?= Html::submitButton(Yii::t('user', 'submit_login'), ['class' => 'btn btn-primary']) ?>
        </div>
    <?php $form::end();
Card::end();

