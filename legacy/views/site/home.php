<?php

/* @var $this \yii\web\View */
/* @var $adminRealms array */

use app\widgets\CardLayout;
use yii\bootstrap4\Html;

$cardItemsRealmAdmin = [];
foreach ($adminRealms as $realmId){
    $cardItemsRealmAdmin[] = [
        'header' => Html::a("Realm $realmId Management", ['group-assertion/create', 'realm' => $realmId]),
        'body' => 'Realm management',
        'roles' => 'realm-management',
        'roleParams' => ['realm' => $realmId]
    ];
}



echo CardLayout::widget([
    'containerOptions' => ['class' => [CardLayout::CARD_DECK]],
    /*
    'defaultCardConfig' => [
        // 'containerOptions' => ['class' => ['col-lg-3']],
        'header' => Faker\Provider\de_DE\Person::firstNameFemale(),
        'headerOptions' => ['class' => []],
        'body' =>  \Faker\Provider\Person::firstNameMale(),
        'roles' => 'permissionNametoShowThisCards
    ],
    */

    'items' => \yii\helpers\ArrayHelper::merge([
        [
            'header' => Html::a('Realms', ['realm/index']),
            'body' => 'Realm management',
            'roles' => 'realm-crud'
        ],
        [
            'header' => Html::a('Domains', ['domain/index']),
            'body' => 'Domain management',
            'roles' => 'realm-crud'
        ],
        [
            'header' => Html::a('Gremien', ['gremien/index']),
            'body' => 'Gremien management',
            'roles' => 'realm-crud'
        ],
        [
            'header' => Html::a('Groups', ['group/index']),
            'body' => 'Groups management',
            'roles' => 'realm-crud',
        ],
        [
            'header' => Html::a('Roles', ['roles/index']),
            'body' => 'Roles management',
            'roles' => 'realm-crud',
        ],
    ], $cardItemsRealmAdmin)
]);

