<?php

use app\models\db\Realm;
use app\models\db\search\GroupSearch;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel GroupSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Gruppen');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="groups-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(FAS::icon('plus') . ' ' . Yii::t('app', 'Gruppe erstellen'), ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [

            'name',
            [
                'attribute' => 'realm_uid',
                'filter' => ArrayHelper::map(Realm::find()->asArray()->all(), 'uid', 'long_name'),
                'filterInputOptions' => ['prompt' => 'Alle Realms', 'class' => 'form-control', 'id' => null]
            ],

            ['class' => ActionColumn::class],
        ],
    ]); ?>


</div>
