<?php

use yii\bootstrap4\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Gremium */
/* @var $form yii\bootstrap4\ActiveForm*/
?>

<div class="gremien-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'realm_uid')->dropDownList(
        ArrayHelper::map(app\models\db\Realm::find()->all(), 'uid', 'uid'),
        [
            'prompt' => 'Wähle...',
            'disabled' => false, // TODO: can view Realms
        ]
    ) ?>

    <?= $form->field($model, 'parent_gremium_id')->dropDownList(
        ArrayHelper::map(app\models\db\Gremium::find()->all(), 'id', 'name'),
        [
            'prompt' => 'Wähle...',
            'disabled' => false, // TODO: can view Realms
        ]
    ) ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Speichern'), ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
