<?php

use yii\db\Migration;

/**
 * Class m210930_002753_crudRoles
 */
class m210931_002753_init_rbac_roles extends Migration
{

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $auth = Yii::$app->getAuthManager();

        $createRelmsPerm = $auth->createPermission('realm-crud');
        $auth->add($createRelmsPerm);

        $superAdminRole = $auth->createRole('OA-Admin');
        $auth->add($superAdminRole);
        $auth->addChild($superAdminRole, $createRelmsPerm);

        $realmAdminRole = $auth->createRole('RealmAdmin');
        $realmAdminRole->ruleName = \app\rbac\rules\RealmAdminRule::class;
        $auth->add($realmAdminRole);

        $realmMgmntPermission = $auth->createPermission('realm-management');
        $auth->add($realmMgmntPermission);
        $auth->addChild($realmAdminRole, $realmMgmntPermission);
        
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        Yii::$app->authManager->removeAllRoles();
        Yii::$app->authManager->removeAllPermissions();
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210930_002753_crudRoles cannot be reverted.\n";

        return false;
    }
    */
}
