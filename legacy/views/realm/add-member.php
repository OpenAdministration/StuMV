<?php

use app\models\db\Realm;
use app\models\db\User;
use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;
use yii\helpers\ArrayHelper;

/* @var $this \yii\web\View */
/* @var $model \app\models\db\RealmAssertion */
/* @var $realms Realm[]|array|\yii\db\ActiveRecord[] */
/* @var $users \app\models\db\User[]|array|\yii\db\ActiveRecord[] */


$this->title = 'Nutzer*in hinzufügen';
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Realms'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->realm_uid, 'url' => ['realm/view', 'id' => $model->realm_uid]];
$this->params['breadcrumbs'][] = $this->title;


$form = ActiveForm::begin();

echo $form->field($model, 'realm_uid')->dropdownList(ArrayHelper::map(
    $realms, 'uid', 'uid'
));
echo $form->field($model, 'user_id')->dropdownList(ArrayHelper::map(
    $users, 'id', static fn(User $u) => "$u->fullName ($u->username)"
));
echo Html::submitButton('Speichern', ['class' => ['btn', 'btn-primary']]);

ActiveForm::end();