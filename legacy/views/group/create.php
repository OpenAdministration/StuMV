<?php

use yii\helpers\Html;
/* @var $this yii\web\View */
/* @var $model app\models\db\Group */

$this->title = Yii::t('app', 'Gruppe erstellen');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Groups'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="groups-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
