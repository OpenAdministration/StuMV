<?php

use yii\grid\ActionColumn;
use yii\grid\SerialColumn;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel \app\models\db\search\RealmSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Realms');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="realms-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(FAS::icon('plus') . ' ' . Yii::t('app', 'Erstelle Realm'), ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [

            'uid',
            'long_name',

            ['class' => ActionColumn::class],
        ],
    ]); ?>


</div>
