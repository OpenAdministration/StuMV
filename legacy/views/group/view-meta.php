<?php

/* @var $this yii\web\View */
/* @var $model app\models\db\Group */

use yii\bootstrap4\Html;
use yii\widgets\DetailView;

echo $this->render('view', ['model' => $model, 'activeTabName' => 'meta']);

?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id',
        'name',
        'realm_uid',
    ],
]) ?>

<p>
    <?= Html::a(Yii::t('app', 'Ändern'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a(Yii::t('app', 'Löschen'), ['delete', 'id' => $model->id], [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => Yii::t('app', 'Bist du sicher das dieses Gremium gelöscht werden soll?'),
            'method' => 'post',
        ],
    ]) ?>
</p>
