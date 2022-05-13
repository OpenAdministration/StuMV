<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\db\Group */
/* @var $activeTabName */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gruppe'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

?>
<div class="groups-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    echo \yii\bootstrap4\Tabs::widget([
        'items' => [
            [
                'label' => 'Zugeordnete Rollen',
                'url' => ['view', 'tab' => 'roles', 'id' => $model->id],
                'active' => 'roles' === $activeTabName,
            ],
            [
                'label' => 'Optionen',
                'url' => ['view', 'tab' => 'meta', 'id' => $model->id],
                'active' => 'meta' === $activeTabName,
            ],
        ],
        'options' => ['class' => ['mb-4']]
    ]); ?>
</div>
