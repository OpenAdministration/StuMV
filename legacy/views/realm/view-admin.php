<?php

use app\models\db\Role;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\SerialColumn;
use yii\bootstrap4\Html;
use yii\data\ActiveDataProvider;

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */


echo $this->render('view', ['model' => $model, 'activeTabName' => 'admin']);


$dataProvider = new ActiveDataProvider([
    'query' => $model->getAdminUsers(),
    'pagination' => [
        'pageSize' => 20,
    ],
]);

echo Html::a(FAS::icon('plus') . ' Person als Admin in Realm hinzfügen', ['realm/add-admin', 'id' => $model->uid], ['class' => ['btn', 'btn-success']]);
echo \yii\grid\GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        ['class' => SerialColumn::class],
        'username',
        'fullName',
    ]
]);


?>