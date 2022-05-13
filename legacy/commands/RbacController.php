<?php


namespace app\commands;


use app\models\db\RealmAdmin;
use app\models\db\RealmAssertion;
use app\models\db\User;
use Yii;
use yii\base\InvalidArgumentException;
use yii\console\ExitCode;
use yii\helpers\Console;

class RbacController extends \yii\console\Controller
{

    public function actionAddRole($role, $user){
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($role);
        if($role === null) {
            throw new InvalidArgumentException("Rolle nicht bekannt");
        }
        $user = User::findIdentity($user);
        if($user === null){
            throw new InvalidArgumentException("User nicht bekannt");
        }
        if($auth->getAssignment($role->name, $user->id) !== null){
            $this->stdout("[WARN] Rolle bereits zugewiesen\n", Console::FG_YELLOW);
            return ExitCode::DATAERR;
        }

        $auth->assign($role, $user->id);
        return ExitCode::OK;

    }

    public function actionAddRealmAdmin($user, $realm){
        $auth = Yii::$app->authManager;
        $role = $auth->getRole('RealmAdmin');
        if($role === null) {
            throw new InvalidArgumentException("Rolle nicht bekannt - conf failure");
        }
        $user = User::findIdentity($user);
        if($user === null){
            throw new InvalidArgumentException("User nicht bekannt");
        }

        if($auth->getAssignment($role->name, $user->id) === null){
            $auth->assign($role, $user->id);
        }

        $ras = new RealmAssertion();
        $ras->realm_uid = $realm;
        $ras->user_id = $user->id;
        $ras->save();

        $rad = new RealmAdmin();
        $rad->user_id = $user->id;
        $rad->realm_uid = $realm;
        $rad->save();
        return ExitCode::OK;
    }

    public function actionPurgeAssignments(){
        $auth = Yii::$app->authManager;
        $auth->removeAllAssignments();
        $this->prompt("Are you really sure you want to delete all assignments? CTRL-C to abort");
        $this->stdout("[OK] Deleted all Assignments\n", Console::FG_GREEN);
    }

    public function actionClearCache(){
        Yii::$app->authManager->invalidateCache();
        return ExitCode::OK;
    }

    public function afterAction($action, $result)
    {
        Yii::$app->authManager->invalidateCache();
        if($result === ExitCode::OK){
            $this->stdout("[OK]\n", Console::FG_GREEN);
        }
        return parent::afterAction($action, $result);
    }
}