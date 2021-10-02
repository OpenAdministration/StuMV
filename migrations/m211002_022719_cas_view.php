<?php

use yii\db\Migration;

/**
 * Class m211002_014915_cas_view
 */
class m211002_022719_cas_view extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        Yii::$app->db->createCommand()->setRawSql(
            "CREATE VIEW `cas_user` AS SELECT `username`, REPLACE(`password`, '$2y$', '$2a$') AS `password` FROM `user`"
        )->execute();

        Yii::$app->db->createCommand()->setRawSql(
            "CREATE VIEW `cas_attributes` AS " .
            //"SELECT `username`, `attribute`, `value`"
            "SELECT `username`, 'realm' AS `attribute`, ra.realm_id AS `value` FROM user 
                    LEFT JOIN realm_assertion AS ra ON ra.user_id = user.id " .
            "UNION 
            SELECT `username`, 'groups' AS `attribute`, g.name AS `value` FROM user 
                    LEFT JOIN `group_assertion` AS ga ON ga.`user_id` = user.id
                    LEFT JOIN `group` AS g ON g.id = ga.`group_id` " .
            "UNION
            SELECT `username`, 'gremien' AS `attribute`, g.name AS `value` FROM user 
                    LEFT JOIN realm_assertion AS ra ON ra.user_id = user.id 
                    LEFT JOIN gremium AS g ON g.belongingRealm = ra.realm_id"
        )->execute();

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        Yii::$app->db->createCommand()->setRawSql(
            "DROP VIEW `cas_user`"
        )->execute();

        Yii::$app->db->createCommand()->setRawSql(
            "DROP VIEW `cas_attributes`"
        )->execute();

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211002_014915_cas_view cannot be reverted.\n";

        return false;
    }
    */
}
