<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Role */

$this->title = Yii::t('app', 'Rolle anlegen');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gremien'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->gremium->name, 'url' => \yii\helpers\Url::to(['gremien/view', 'id' => $model->gremium_id])];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="role-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
