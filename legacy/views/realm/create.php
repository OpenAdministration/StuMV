<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */

$this->title = Yii::t('app', 'Realm erstellen');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Realms'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="realms-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
