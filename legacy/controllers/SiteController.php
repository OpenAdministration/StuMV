<?php


namespace app\controllers;


use app\models\db\RealmAdmin;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ErrorAction;

class SiteController extends Controller
{

    public function behaviors() : array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => false,
                        'actions' => ['profile'],
                        'roles' => ['?'], // guests only
                    ],
                    [
                        'allow' => true,
                        'actions' => ['profile'],
                        'roles' => ['@'], // logged in only
                    ],
                    [
                        'allow' => true,
                        'actions' => [], // rest: ok
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    public function actionHome()
    {
        if(Yii::$app->getUser()->isGuest){
            return $this->redirect(['auth/register']);
        }

        $adminRealms = RealmAdmin::find()
            ->select('realm_uid')
            ->where(['user_id' => Yii::$app->user->getId()])
            ->column();

        if(count($adminRealms) > 0){
            return $this->redirect(['gremien/index']);
            //return $this->render('home', [ 'adminRealms' => $adminRealms]);
        }else{
            return $this->redirect(['site/profile']);
        }

    }

    public function actionProfile() : string
    {
        return $this->render('profile', ['user' => Yii::$app->user->getIdentity()]);
    }

    public function actionImpressum(){
        //TODO fillme
    }

    public function actionDatenschutz(){
        //TODO fillme
    }
}