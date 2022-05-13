<?php


namespace app\widgets;

use app\models\db\User;
use rmrevin\yii\fontawesome\FAR;
use rmrevin\yii\fontawesome\FAS;
use Yii;
use yii\bootstrap4\Nav;
use yii\bootstrap4\NavBar;
use yii\helpers\Html;
use yii\helpers\VarDumper;


/**
 * Class NavBar4
 * @package app\widgets
 */
class NavBar4 extends \yii\bootstrap4\Widget
{
    public function run()
    {
        NavBar::begin([
            'brandLabel' => Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            //'brandImage' => FAS::icon('chevron-left'),
            'options' => [
                'class' => 'navbar-dark bg-dark navbar-expand-md navbar-fixed-top',
            ],
            'renderInnerContainer' => false,
        ]);


        $user = Yii::$app->user;
        $isLoggedIn = !$user->isGuest;
        /* @var User $id */
        $id = $user->getIdentity();
        $nameTag = $id->fullName ?? $id->username ?? 'Anonymous?!';
        $isSuperAdmin = $id?->isSuperAdmin() ?? false;
        echo Nav::widget([
            'options' => ['class' => 'navbar-nav mr-auto'],
            'items' => [
                [
                    'label' => FAS::icon('globe') . ' Realms ' . FAS::icon('dragon')->size('xs'),
                    'url' => ['realm/'],
                    'visible' => $isSuperAdmin,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('sitemap') . ' Gremien',
                    'url' => ['gremien/'],
                    'visible' => $isLoggedIn,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('users') . ' Personen',
                    'url' => ['user/'],
                    'visible' => $isLoggedIn,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('circle-notch') . ' Gruppen ' . FAS::icon('dragon')->size('xs'),
                    'url' => ['group/'],
                    'visible' => $isSuperAdmin,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('satellite-dish') . ' Domains ' . FAS::icon('dragon')->size('xs'),
                    'url' => ['domain/'],
                    'visible' => $isSuperAdmin,
                    'encode' => false,
                ],
            ]
        ]);
        if($isLoggedIn && !$isSuperAdmin){
            // parse the mayor role of the user
            $adminRealms = $id->adminRealms;
            if(!empty($adminRealms)){
                $adminRealms = array_map(static fn($realm) => $realm->uid, $adminRealms);
                $adminLabel = implode(', ' , $adminRealms);
            }
            $memberRealms = $id->realms;
            if(!empty($memberRealms)){
                $memberRealms = array_diff(array_map(static fn($realm) => $realm->uid, $memberRealms), $adminRealms);
                $memberLabel = implode(', ' , $memberRealms);
            }
        }
        // align on the right side with ml-auto
        echo Nav::widget([
            'options' => ['class' => 'navbar-nav ml-auto'],
            'items' => [
                [
                    'label' => FAS::icon('sign-in-alt') . ' Login',
                    'url' => ['auth/wayfinder'],
                    'visible' => !$isLoggedIn,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('user-plus') . ' Registrieren',
                    'url' => ['auth/register'],
                    'visible' => !$isLoggedIn,
                    'encode' => false,
                ],
                [
                    'label' => FAS::icon('dragon') . ' Superadmin',
                    'visible' => $isSuperAdmin,
                    'encode' => false,
                    'url' => false,
                    'options' => [
                        'title' => 'Superadmin'
                    ]
                    //'disabled' => true,
                ],
                [
                    'label' => FAS::icon('user-cog') . ' ' . ($adminLabel ?? ''),
                    'visible' => $isLoggedIn && !$isSuperAdmin && !empty($adminLabel),
                    'url' => false,
                    'encode' => false,
                    'options' => [
                        'title' => 'Admin in ' . ($adminLabel ?? '-'),
                    ]
                    //'disabled' => true,
                ],
                [
                    'label' => FAS::icon('user-tag') . ' ' . ($memberLabel ?? ''),
                    'visible' => $isLoggedIn && !$isSuperAdmin && !empty($memberLabel),
                    'encode' => false,
                    'url' => false,
                    'options' => [
                        'title' => 'Mitglied in ' . ($memberLabel ?? '-')
                    ]
                    //'disabled' => true,
                ],
                [
                    'label' => Html::img(Yii::$app->request->baseUrl . '/img/dummyPerson.svg', ['height' => 30, 'class' => 'pr-1']) . ' ' . $nameTag,
                    'visible' => $isLoggedIn,
                    'dropdownOptions' => ['class' => 'dropdown-menu-right'],
                    'encode' => false,
                    'items' => [
                        [
                            'label' => FAS::icon('user-circle') . ' ' . Yii::t('user', 'action_showProfile_label'),
                            'url' => ['site/profile'],
                            'encode' => false,
                        ],
                        [
                            'label' => FAS::icon('sign-out-alt') . ' ' . Yii::t('user', 'action_logout_label'),
                            'url' => ['auth/logout'],
                            'encode' => false,
                        ],
                    ],
                ],
            ],
        ]);

        NavBar::end();
    }
}