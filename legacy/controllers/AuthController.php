<?php


namespace app\controllers;


use app\components\auth\AuthComponent;
use app\models\db\Domain;
use app\models\db\Realm;
use app\models\db\RealmAssertion;
use app\models\db\User;
use Yii;
use yii\base\Model;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

class AuthController extends Controller
{
    public $defaultAction = 'wayfinder';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['login', 'logout', 'register', 'wayfinder'],
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['login', 'register', 'wayfinder'],
                        'roles' => ['?'], // guests only
                    ],
                    [
                        'allow' => true,
                        'actions' => ['logout'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws HttpException
     * @return Response | string
     */
    public function actionLogin(string $service)
    {
        Yii::info("start login action");

        /** @var AuthComponent $authComponent */
        $authComponent = Yii::$app->authServices;
        $authComponent->setAuthService($service);
        $auth = $authComponent->getAuthService();

        $auth->forceAuthentication();
        $username = $auth->getUsername();
        Yii::debug($username);
        if ($username) {
            $user = (Yii::$app->user->identityClass)::findIdentityByUsername($username);
            Yii::debug($user);
            if ($user) {
                $loginSuccess = Yii::$app->user->login($user);
                if($loginSuccess) {
                    Yii::$app->getSession()->set('auth-service', $service); // remember which service was used for auth
                    return $this->redirect(['site/home']);
                    //return $this->render('debug', ['auth' => $username]);
                }
                throw new HttpException(403, 'Login gescheitert');
            } else {
                throw new HttpException(403, 'Der User wurde in der Datenbank nicht gefunden');
            }
        }
        return $this->render('debug', ['auth' => $username]);
    }

    public function actionLogout() : Response
    {
        /** @var AuthComponent $authServices */
        $authServices = Yii::$app->authServices;
        $auth = $authServices->getAuthService();
        if (!Yii::$app->getUser()->isGuest) {
            Yii::$app->getUser()->logout(true);
            $auth->logout(Url::home(true));
        }
        // In case the logout fails (not authenticated)
        return $this->redirect(Url::home(true));
    }

    /**
     * @return string|Response
     */
    public function actionWayfinder()
    {
        /** @var AuthComponent $auth */
        $auth = Yii::$app->authServices;
        $authNames = $auth->getPossibleAuthWays();

        if(count($authNames) === 1){
            $this->redirect(['auth/login',  'service' => $authNames[0]]);
        }

        return $this->render('wayfinder', ['authNames' => $authNames]);
    }

    public function actionConfirm(string $token) : string
    {
        $users = User::findAll(['token' => $token]);
        if(count($users) === 1 && $users[0]->status === User::STAUTS_UNVERIVIED){
            $user = $users[0];
            $user->status = User::STAUTS_VERIVIED;
            $user->token = null;
            $success = $users[0]->save();
            if ($success) {
                Yii::$app->getSession()->addFlash('success', 'Account wurde aktiviert');
                // get fitting Domain
                [,$domGiven] = explode('@', $user->email);
                $dom = Domain::findOne(['name' => $domGiven, 'forRegistration' => 1]);
                if($dom !== null){
                    /** @var Realm $realm */
                    $realmUid = $dom->realm_uid;
                    $rAssert = new RealmAssertion();
                    $rAssert->realm_uid = $realmUid;
                    $rAssert->user_id = $user->id;
                    $realmAsserted = $rAssert->save();
                    if($realmAsserted){
                        Yii::$app->getSession()->addFlash('success', 'Realm wurde hinzugefügt');
                    }else{
                        Yii::$app->getSession()->addFlash('error', 'Realm wurde NICHT hinzugefügt. Bitte melde dich bei einem Admin');
                    }
                }
            } else {
                Yii::$app->getSession()->addFlash('error', 'Ein Datenbank Fehler ist aufgetreten');
            }
        } else if (count($users) === 1 && $users[0]->status !== User::STAUTS_UNVERIVIED) {
            Yii::$app->getSession()->addFlash('info', 'Account wurde bereits aktiviert');
        } else {
            Yii::$app->getSession()->addFlash('error', 'Der Account kann nicht gefunden werden');
        }
        return $this->render('confirm');

    }

    public function actionRegister()
    {
        if (!Yii::$app->getUser()->isGuest){
            return $this->goHome();
        }

        $model = new User();
        $model->scenario = User::SCENARIO_REGISTER;
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $security = Yii::$app->security;
            $pw = $model->password;
            $hash = $security->generatePasswordHash($pw);
            $model->password = $hash;
            $model->status = User::STAUTS_UNVERIVIED;
            $model->token = $security->generateRandomString();
            $model->authKey = $security->generateRandomString();
            $model->fullName = ucwords(str_replace('.', ' ', explode('@', $model->email)[0]));
            $model->scenario = Model::SCENARIO_DEFAULT; // makes sure password comparison will not be triggered again
            $saved = $model->save();

            // send mail if saved
            $mailResult = $saved && Yii::$app->getMailer()
                    ->compose('@mail/registration-confirmation', [
                        'token' => $model->token,
                        'username' => $model->username,
                    ])
                    //->setFrom()
                    ->setTo($model->email)
                    ->setSubject('Bestätige dein OA-Konto')
                    ->send();
            if($mailResult){
                Yii::$app->getSession()->addFlash(
                    'success',
                    'Die Bestätigungsmail wurde versendet. Bitte bestätige deine Mailadresse, vorher ist ein Login nicht möglich.'
                );
                $model = new User(); // removes old entries from form
                $model->scenario = User::SCENARIO_REGISTER;
            } else {
                Yii::$app->getSession()->addFlash(
                    'error',
                    'Die Bestätigungsmail konnte nicht versand werden. Registration fehlgeschlagen.'
                );
                $model->scenario = User::SCENARIO_REGISTER;
                $model->password = $pw;
            }
        }
        return $this->render('register', ['model' => $model]);
    }
}