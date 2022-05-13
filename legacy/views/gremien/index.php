<?php

use app\models\db\Gremium;
use app\models\db\Realm;
use app\models\db\search\GremiumSearch;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\ActionColumn;
use yii\grid\SerialColumn;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel GremiumSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $realms Realm[] */
/* @var $canCreate bool */

$this->title = Yii::t('app', 'Gremien Auswahl');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="gremien-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php if($canCreate){ ?>
        <p>
            <?= Html::a(FAS::icon('plus') . ' ' . Yii::t('app', 'Erstelle Gremium'), ['create'], ['class' => 'btn btn-success']) ?>
        </p>
    <?php } ?>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'name',
                'value' => static fn(Gremium $g) => Html::a($g->name, ['gremien/view', 'id' => $g->id]),
                'format' => 'html',
            ],[
                'attribute' => 'realm_uid',
                'filter' => ArrayHelper::map($realms, 'uid', 'long_name'),
                'filterInputOptions' => ['prompt' => 'Alle Realms', 'class' => 'form-control', 'id' => null]
            ],[
                'attribute' => 'parent_gremium_id',
                'value' => static function(Gremium $g) {
                    if(empty($g->parent_gremium_id)){
                        return null;
                    }
                    return Html::a($g->parentGremium?->name, ['gremien/view', 'id' => $g->parent_gremium_id]);
                },
                'format' => 'html',
            ],[
                'class' => ActionColumn::class
            ],
        ],
    ]); ?>


</div>
