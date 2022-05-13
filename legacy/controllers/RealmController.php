<?php

namespace app\controllers;

use app\models\db\Realm;
use app\models\db\RealmAdmin;
use app\models\db\RealmAssertion;
use app\models\db\search\RealmSearch;
use app\models\db\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Request;
use yii\web\Response;

/**
 * RealmController implements the CRUD actions for Realms model.
 */
class RealmController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors() : array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => Yii::$app->user->identity->isSuperAdmin(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all Realms models.
     * @return string
     */
    public function actionIndex() : string
    {
        $searchModel = new RealmSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Realms model.
     * @param string $id
     * @param string $tab
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(string $id, string $tab = 'meta') : string
    {
        return match($tab){
            'meta' => $this->render('view-meta', [
                'model' => $this->findModel($id),
            ]),
            'member' => $this->render('view-member', [
                'model' => $this->findModel($id),
            ]),
            'admin' => $this->render('view-admin', [
                'model' => $this->findModel($id),
            ])
        };
    }

    public function actionAddAdmin(string $id) : string|Response
    {
        $group = $this->findModel($id);
        $model = new RealmAdmin();

        if($model->load(Yii::$app->request->post()) && $model->save()){
            return $this->redirect(['realm/view', 'id' => $id, 'tab' => 'admin']);
        }
        $model->realm_uid = $id;

        $realms = Realm::find()->all();
        $users = User::find()->innerJoin('realm_assertion', 'user_id = id')->where(['realm_uid' => $id])->all();

        return $this->render('add-admin', [
            'model' => $model,
            'realms' => $realms,
            'users' => $users,
        ]);
    }

    public function actionAddMember(string $id) : string|Response
    {
        $group = $this->findModel($id);
        $model = new RealmAssertion();

        if($model->load(Yii::$app->request->post()) && $model->save()){
            return $this->redirect(['realm/view', 'id' => $id, 'tab' => 'member']);
        }
        $model->realm_uid = $id;

        $realms = Realm::find()->all();
        $users = User::find()->all();

        return $this->render('add-member', [
            'model' => $model,
            'realms' => $realms,
            'users' => $users,
        ]);
    }

    /**
     * Creates a new Realms model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return Response|string
     */
    public function actionCreate() : Response|string
    {
        $model = new Realm();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->uid]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Realms model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(string $id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->uid]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Realms model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete(string $id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Realms model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Realm the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(string $id) : Realm
    {
        if (($model = Realm::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }

}
