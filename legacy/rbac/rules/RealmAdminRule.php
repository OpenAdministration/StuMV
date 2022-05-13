<?php


namespace app\rbac\rules;



use app\models\db\RealmAdmin;

class RealmAdminRule extends \yii\rbac\Rule
{
    public $name = "realmAdmin";

    /**
     * @inheritDoc
     */
    public function execute($user, $item, $params)
    {
        $realm = $params['realm'];
        $result = RealmAdmin::findOne(['realm_id' => $realm, 'user_id' => $user]);
        return $result !== null;
    }
}