<?php

use yii\db\Migration;

/**
 * Class m220121_041222_usingRoles
 */
class m220121_041222_usingRoles extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropForeignKey('group_assertion_user_id_fk', 'group_assertion');
        $this->dropPrimaryKey('PRIMARY', 'group_assertion');
        $this->dropColumn('group_assertion', 'user_id');
        $this->addColumn('group_assertion', 'role_id', $this->integer());
        $this->addForeignKey('fk_group_assertion', 'group_assertion', 'role_id', 'role', 'id');
        $this->addPrimaryKey('PRIMARY', 'group_assertion', ['group_id', 'role_id']);
        $this->createTable('role_assertion', [
            'role_id' => $this->integer(),
            'user_id' => $this->integer(),
            'from' => $this->date()->notNull(),
            'until' => $this->date(),
        ]);
        $this->addForeignKey('fk_role_assertion_role', 'role_assertion', 'role_id', 'role', 'id');
        $this->addForeignKey('fk_role_assertion_user', 'role_assertion', 'user_id', 'user', 'id');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220121_041222_usingRoles cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220121_041222_usingRoles cannot be reverted.\n";

        return false;
    }
    */
}
