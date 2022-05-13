<?php

use yii\db\Migration;

/**
 * Class m220123_040124_rephraseColumns
 */
class m220123_040124_rephraseColumns extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->renameColumn('realm_admin', 'realm_id', 'realm_uid');
        $this->renameColumn('realm_assertion', 'realm_id', 'realm_uid');
        $this->renameColumn('domain', 'belongingRealm', 'realm_uid');
        $this->renameColumn('gremium', 'belongingRealm', 'realm_uid');
        $this->renameColumn('group', 'belongingRealm', 'realm_uid');
        $this->renameColumn('gremium', 'parentGremium', 'parent_gremium_id');
        $this->renameColumn('role', 'belongingGremium', 'gremium_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->renameColumn('domain', 'realm_uid', 'belongingRealm');
        $this->renameColumn('gremium', 'realm_uid', 'belongingRealm');
        $this->renameColumn('gremium','parent_gremium_id', 'parentGremium');
        $this->renameColumn('group',  'realm_uid', 'belongingRealm');
        $this->renameColumn('role',  'gremium_id', 'belongingGremium');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220123_040124_rephraseColumns cannot be reverted.\n";

        return false;
    }
    */
}
