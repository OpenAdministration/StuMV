<?php

use app\models\db\Role;
use app\models\db\User;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\SerialColumn;
use yii\bootstrap4\Html;
use yii\data\ActiveDataProvider;

/* @var $this yii\web\View */
/* @var $model app\models\db\Realm */


echo $this->render('view', ['model' => $model, 'activeTabName' => 'member']);


$dataProvider = new ActiveDataProvider([
    'query' => $model->getAssertedUsers(),
    'pagination' => [
        'pageSize' => 20,
    ],
]);

echo Html::a(FAS::icon('plus') . ' Person in Realm hinzfügen', ['realm/add-member', 'id' => $model->uid], ['class' => ['btn', 'btn-success']]);
echo \yii\grid\GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        ['class' => SerialColumn::class],
        'username',
        'fullName',
    ]
]);


?>