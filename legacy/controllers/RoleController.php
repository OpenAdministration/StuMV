<?php

namespace app\controllers;

use app\models\db\Gremium;
use app\models\db\RoleAssertion;
use app\models\db\User;
use Yii;
use app\models\db\Role;
use yii\db\Query;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * RoleController implements the CRUD actions for Role model.
 */
class RoleController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors() : array
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
                        'actions' => ['view', 'add-user'],
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
     * Displays a single Role model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView(int $id) : string
    {
        $role = $this->findModel($id);
        $q = (new Query())
            ->from('user')
            ->innerJoin('role_assertion', 'user_id = id')
            ->where(['role_id' => $role->id]);
        $dp = new \yii\data\ActiveDataProvider([
            'query' => $q,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
        return $this->render('view', [
            'model' => $role,
            'dataProvider' => $dp,
        ]);
    }

    /**
     * Creates a new Role model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @param int $gremiumId
     * @return string|Response
     */
    public function actionCreate(int $gremiumId): string|Response
    {
        $model = new Role();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['gremien/view', 'id' => $gremiumId]);
        }
        $gremium = Gremium::findOne($gremiumId);
        $model->gremium_id = $gremiumId;
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Role model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return string|Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate(int $id): string|Response
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
     * @throws NotFoundHttpException
     */
    public function actionAddUser(int $roleId) : Response|string
    {
        $role = $this->findModel($roleId);
        $assertion = new RoleAssertion();
        $assertion->role_id = $roleId;
        if ($assertion->load(Yii::$app->request->post()) && $assertion->save()) {
            return $this->redirect(['gremien/view', 'id' => $role->gremium_id]);
        }
        return $this->render('assert-user', [
            'role' => $role,
            'model' => $assertion,
            'users' => User::find()
                ->innerJoin('realm_assertion', 'user_id = id')
                // only ppl in the realm of the gremium can be added
                ->where(['realm_assertion.realm_uid' => $role->gremium->realm_uid])
                // filter all users out wich are already in the role (dates are ignored rn)
                ->andWhere(['not in', 'id', RoleAssertion::find()->select('user_id')->where(['role_id' => $role->id])])
                ->all(),
        ]);
    }

    /**
     * Deletes an existing Role model.
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
     * Finds the Role model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Role the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel(int $id): Role
    {
        if (($model = Role::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app', 'The requested page does not exist.'));
    }
}
