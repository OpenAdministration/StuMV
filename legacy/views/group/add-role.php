<?php

use app\models\db\Group;
use app\models\db\Role;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\helpers\ArrayHelper;

/* @var $this \yii\web\View */
/* @var $model \app\models\db\GroupAssertion */
/* @var $roles Role[] */
/* @var $groups Group[] */

$this->title = 'Rolle hinzufügen';
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gruppen'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->group->name, 'url' => ['group/view', 'id' => $model->group->id]];
$this->params['breadcrumbs'][] = $this->title;


$form = ActiveForm::begin();

echo $form->field($model, 'group_id')->dropdownList(ArrayHelper::map(
    $groups, 'id', static fn(Group $g) => "$g->name ($g->realm_uid)"
));
echo $form->field($model, 'role_id')->dropdownList(ArrayHelper::map(
    $roles, 'id', static fn(Role $r) => "$r->name ({$r->gremium->realm_uid} - {$r->gremium->name})"
));
echo Html::submitButton('Speichern', ['class' =>['btn', 'btn-primary']]);

ActiveForm::end();