<?php

use app\models\db\User;
use rmrevin\yii\fontawesome\FAS;
use yii\grid\ActionColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\db\search\UserSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Nutzer*innen');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(FAS::icon('envelope-open-text') . ' ' . Yii::t('app', 'Nutzer*in einladen'), ['invite'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            //'id',
            'fullName',
            'username',
            [
                'label' => 'Realm(s) ' . FAS::icon('dragon')->size('xs'),
                'value' => static function(User $user){
                    return implode(', ', ArrayHelper::getColumn($user->realms, 'uid'));
                },
                'visible' => Yii::$app->user->identity->isSuperAdmin(),
                'encodeLabel' => false,
            ],
            'email:email',
            [
                'label' => 'Status',
                'attribute' => 'status',
                'value' => static function(User $user){
                    return match ($user->status){
                        0 => FAS::icon('hourglass-half') . ' Email nicht bestätigt',
                        1 => FAS::icon('check') . ' Email bestätigt',
                        2 => FAS::icon('cross') . ' Blockiert',
                    };
                },
                'format' => 'html',
                'filter' => [
                    0 => 'E-Mail nicht bestätigt',
                    1 => 'E-Mail bestätigt',
                    2 => 'Blockiert',
                ],
                'filterInputOptions' => ['prompt' => 'Alle Nutzer', 'class' => 'form-control', 'id' => null]
            ],
            //'phone',
            //'iban',
            //'adresse',
            //'profilePic',

            ['class' => ActionColumn::class],
        ],
    ]); ?>


</div>
