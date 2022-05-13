<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */

$this->title = Yii::t('app', 'Update Realms: {name}', [
    'name' => $model->uid,
]);
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Realms'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->uid, 'url' => ['view', 'id' => $model->uid]];
$this->params['breadcrumbs'][] = Yii::t('app', 'Update');
?>
<div class="realms-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
