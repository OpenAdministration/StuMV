<?php

use yii\data\ActiveDataProvider;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Role */
/* @var $dataProvider ActiveDataProvider */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gremien'), 'url' => ['gremien/index']];
$this->params['breadcrumbs'][] = ['label' => $model->gremium->name, 'url' => ['gremien/view', 'id' => $model->gremium_id]];
$this->params['breadcrumbs'][] = $this->title;

\yii\web\YiiAsset::register($this);
?>
<div class="role-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(Yii::t('app', 'Update'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a(Yii::t('app', 'Delete'), ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= \yii\grid\GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => \yii\grid\SerialColumn::class ],
            [
                'label' => 'Nutzer*in',
                'value' => static fn($userRoleJoin) => $userRoleJoin['fullName'] . ' (' . $userRoleJoin['username'] . ')',
            ],
            'from:date',
            'until:date',
            // TODO: ActionColumn
        ],
    ]) ?>

</div>
