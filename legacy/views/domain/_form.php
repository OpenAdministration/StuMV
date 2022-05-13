<?php

use yii\bootstrap4\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Domain */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="domains-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'activeMail')->dropDownList([
        0 => 'Deaktiviert',
        1 => 'Aktivieren',
    ]) ?>

    <?= $form->field($model, 'realm_uid')->dropDownList(
        ArrayHelper::map(app\models\db\Realm::find()->all(), 'uid', 'uid'),
        [
            'prompt' => 'Wähle...',
            'disabled' => false, // TODO: can view Realms
        ]
    ) ?>

    <?= $form->field($model, 'forRegistration')->dropDownList([
        0 => 'Deaktiviert',
        1 => 'Aktivieren',
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Save'), ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
