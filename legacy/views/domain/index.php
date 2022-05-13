<?php

use app\models\db\Domain;
use app\models\db\Realm;
use app\models\db\search\DomainSearch;
use rmrevin\yii\fontawesome\FAR;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel DomainSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Domains');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="domains-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(FAS::icon('plus') . ' ' . Yii::t('app', 'Domain anlegen'), ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]);
    ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            [
                'attribute' => 'realm_uid',
                'filter' => ArrayHelper::map(Realm::find()->asArray()->all(), 'uid', 'long_name'),
                'filterInputOptions' => ['prompt' => 'Alle Realms', 'class' => 'form-control', 'id' => null]
            ],
            'name:url',
            [
                'attribute' => 'forRegistration',
                'value' => static fn(Domain $domain) => match ($domain->forRegistration){
                    1 => FAR::icon('check-circle'),
                    0 => FAR::icon('ban'),
                },
                'format' => 'html',
            ],
            [
                'attribute' => 'activeMail',
                'value' => static fn(Domain $domain) => match ($domain->activeMail){
                    1 => FAR::icon('check-circle'),
                    0 => FAR::icon('ban'),
                },
                'format' => 'html',
            ],


            ['class' => ActionColumn::class],
        ],
    ]); ?>


</div>
