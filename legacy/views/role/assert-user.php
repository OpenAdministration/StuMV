<?php

/* @var $this \yii\web\View */
/* @var $role \app\models\db\Role */
/* @var $model \app\models\db\RoleAssertion */
/* @var $users \app\models\db\User[] */


use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\helpers\ArrayHelper;

$this->title = Yii::t('app', "'$role->name' zuweisen");
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gremien'), 'url' => ['gremien/index']];
$this->params['breadcrumbs'][] = ['label' => $role->gremium->name, 'url' => ['gremien/view', 'id' => $role->gremium->id]];
$this->params['breadcrumbs'][] = ['label' => $role->name, 'url' => ['role/view', 'id' => $role->id]];
$this->params['breadcrumbs'][] = 'zuweisen';

?>
<div class="role-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    $form = ActiveForm::begin();

    echo $form->field($model, 'user_id')->dropdownList(
        ArrayHelper::map($users, 'id', static fn(\app\models\db\User $user) => $user->fullName . ' ('.$user->username.')')
    );
    echo $form->field($model, 'from')->input('date', ['value' => date_create()->format('Y-m-d')]);
    echo $form->field($model, 'until')->input('date');

    echo Html::submitButton('Speichern', ['class' => ['btn', 'btn-primary']]);

    ActiveForm::end();
    ?>
</div>


