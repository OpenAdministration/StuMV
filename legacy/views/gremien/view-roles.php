<?php

use app\models\db\Role;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\SerialColumn;
use yii\bootstrap4\Html;
use yii\data\ActiveDataProvider;

/* @var $this yii\web\View */
/* @var $model app\models\db\Gremium */


echo $this->render('view', ['model' => $model, 'activeTabName' => 'roles']);


$dataProvider = new ActiveDataProvider([
    'query' => $model->getRoles(),
    'pagination' => [
        'pageSize' => 20,
    ],
]);

echo Html::a(FAS::icon('plus') . ' Rolle erstellen', ['role/create', 'gremiumId' => $model->id], ['class' => ['btn', 'btn-success']]);
echo \yii\grid\GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        //['class' => SerialColumn::class],
        'name',
        [
            'label' => 'Nutzer*innen',
            'value' => static function (Role $role) {
                $users = $role->assertedUsers;
                $res = array_map(static fn (\app\models\db\User $user) => $user->fullName, $users);
                $add = Html::a(FAS::icon('plus'), ['role/add-user', 'roleId' => $role->id]);
                return implode(', ', $res) . ' ' . $add;
            },
            'format' => 'html'
        ],
        [
            'class' => \yii\grid\ActionColumn::class,
            'controller' => 'role',
        ]
    ]
]);


?>