<?php

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */

use yii\bootstrap4\Html;
use yii\widgets\DetailView;

echo $this->render('view', ['model' => $model, 'activeTabName' => 'meta']);

?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'uid',
        'long_name',
    ],
]) ?>

<p>
    <?= Html::a(Yii::t('app', 'Ändern'), ['update', 'id' => $model->uid], ['class' => 'btn btn-primary']) ?>
    <?= Html::a(Yii::t('app', 'Löschen'), ['delete', 'id' => $model->uid], [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => Yii::t('app', 'Bist du sicher das dieses Gremium gelöscht werden soll?'),
            'method' => 'post',
        ],
    ]) ?>
</p>
