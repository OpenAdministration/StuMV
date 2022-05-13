<?php

namespace app\controllers;

use app\models\db\Gremium;
use app\models\db\search\GremiumSearch;
use Yii;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * GremienController implements the CRUD actions for Gremien model.
 */
class GremienController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => Yii::$app->user->identity->isSuperAdmin(),
                        'actions' => ['create', 'update', 'delete'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['index', 'view'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Gremien models.
     * @return string
     */
    public function actionIndex() : string
    {
        $searchModel = new GremiumSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'realms' => Yii::$app->user->identity->realms,
            'canCreate' => Yii::$app->user->identity->isSuperAdmin(),
        ]);
    }

    /**
     * Displays a single Gremien model.
     * @param integer $id db identifier
     * @param string $tab tab identifier
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(int $id, string $tab = 'roles') : string
    {
        return match($tab){
            'meta' => $this->render('view-meta', [
                'model' => $this->findModel($id),
            ]),
            'roles' => $this->render('view-roles', [
                'model' => $this->findModel($id),
            ])
        };
    }

    /**
     * Creates a new Gremien model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return Response|string
     */
    public function actionCreate() : Response|string
    {
        $model = new Gremium();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Gremien model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return Response|string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(int $id) : Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Gremien model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return Response
     * @throws NotFoundHttpException|StaleObjectException if the model cannot be found
     */
    public function actionDelete(int $id) : Response
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Gremien model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Gremium the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id) : Gremium
    {
        $identity = Yii::$app->user->identity;
        $aq = Gremium::find()->where(['id' => $id]);
        if(!$identity->isSuperAdmin()){
            $aq->andWhere(['realm_uid' => $identity->realmUids]);
        }
        if (($model = $aq->one()) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
