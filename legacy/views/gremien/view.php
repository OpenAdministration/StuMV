<?php


/* @var $this yii\web\View */
/* @var $model app\models\db\Gremium */
/* @var $activeTabName string */

use yii\bootstrap4\Html;

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Gremien'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

echo Html::tag('h1', Html::encode($this->title));

echo \yii\bootstrap4\Tabs::widget([
    'items' => [
        [
            'label' => 'Rollen',
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
]);

