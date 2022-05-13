<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */
/* @var $activeTabName */


$this->title = $model->long_name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Realms'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>

<div class="groups-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    echo \yii\bootstrap4\Tabs::widget([
        'items' => [
            [
                'label' => 'Optionen',
                'url' => ['view', 'tab' => 'meta', 'id' => $model->uid],
                'active' => 'meta' === $activeTabName,
            ],
            [
                'label' => 'Mitglieder',
                'url' => ['view', 'tab' => 'member', 'id' => $model->uid],
                'active' => 'member' === $activeTabName,
            ],
            [
                'label' => 'Admins',
                'url' => ['view', 'tab' => 'admin', 'id' => $model->uid],
                'active' => 'admin' === $activeTabName,
            ],
        ],
        'options' => ['class' => ['mb-4']]
    ]); ?>
</div>
