<?php

use yii\db\Schema;
use yii\db\Migration;

class m211002_022718_base_scheme extends Migration
{

    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        $tableOptions = 'ENGINE=InnoDB';

        $this->createTable('{{%domain}}',[
            'id'=> $this->primaryKey(11),
            'activeMail'=> $this->tinyInteger(1)->notNull()->defaultValue(0),
            'belongingRealm'=> $this->string(32)->notNull(),
            'forRegistration'=> $this->tinyInteger(1)->notNull(),
            'name'=> $this->string(128)->notNull(),
        ], $tableOptions);

        $this->createIndex('name','{{%domain}}',['name'],true);
        $this->createIndex('fk_domains_realms1','{{%domain}}',['belongingRealm'],false);

        $this->createTable('{{%gremium}}',[
            'id'=> $this->primaryKey(11),
            'name'=> $this->string(128)->notNull(),
            'belongingRealm'=> $this->string(32)->notNull(),
            'parentGremium'=> $this->integer(11)->null()->defaultValue(null),
        ], $tableOptions);

        $this->createIndex('gremien_realms_uid_fk','{{%gremium}}',['belongingRealm'],false);
        $this->createIndex('gremien_gremien_id_fk','{{%gremium}}',['parentGremium'],false);

        $this->createTable('{{%group}}',[
            'id'=> $this->primaryKey(11),
            'name'=> $this->string(64)->notNull(),
            'belongingRealm'=> $this->string(32)->notNull(),
        ], $tableOptions);

        $this->createIndex('group_realm_uid_fk','{{%group}}',['belongingRealm'],false);

        $this->createTable('{{%group_assertion}}',[
            'user_id'=> $this->integer(11)->notNull(),
            'group_id'=> $this->integer(11)->notNull(),
        ], $tableOptions);

        $this->createIndex('group_assertion_userid','{{%group_assertion}}',['user_id'],false);
        $this->createIndex('fk_group_assertions_realms','{{%group_assertion}}',['group_id'],false);
        $this->addPrimaryKey('pk_on_group_assertion','{{%group_assertion}}',['user_id','group_id']);

        $this->createTable('{{%realm}}',[
            'uid'=> $this->string(32)->notNull(),
            'long_name'=> $this->string(128)->notNull(),
        ], $tableOptions);

        $this->createIndex('uid','{{%realm}}',['uid'],true);
        $this->addPrimaryKey('pk_on_realm','{{%realm}}',['uid']);

        $this->createTable('{{%realm_admin}}',[
            'user_id'=> $this->integer(11)->notNull(),
            'realm_id'=> $this->string(16)->notNull(),
        ], $tableOptions);

        $this->createIndex('realm_admin_realm_uid_fk','{{%realm_admin}}',['realm_id'],false);
        $this->addPrimaryKey('pk_on_realm_admin','{{%realm_admin}}',['user_id','realm_id']);

        $this->createTable('{{%realm_assertion}}',[
            'user_id'=> $this->integer(11)->notNull(),
            'realm_id'=> $this->string(32)->notNull(),
        ], $tableOptions);

        $this->createIndex('realm_assertion_userid','{{%realm_assertion}}',['user_id'],false);
        $this->createIndex('fk_realm_assertions_realm','{{%realm_assertion}}',['realm_id'],false);
        $this->addPrimaryKey('pk_on_realm_assertion','{{%realm_assertion}}',['user_id','realm_id']);

        $this->createTable('{{%role}}',[
            'id'=> $this->primaryKey(11),
            'name'=> $this->string(64)->null()->defaultValue(null),
            'belongingGremium'=> $this->integer(11)->notNull(),
        ], $tableOptions);

        $this->createIndex('roles_gremien_id_fk','{{%role}}',['belongingGremium'],false);

        $this->createTable('{{%user}}',[
            'id'=> $this->primaryKey(11),
            'fullName'=> $this->string(64)->null()->defaultValue(null),
            'username'=> $this->string(32)->notNull(),
            'email'=> $this->string(64)->notNull(),
            'status'=> $this->tinyInteger(3)->notNull(),
            'password'=> $this->string(60)->notNull(),
            'phone'=> $this->string(32)->null()->defaultValue(null),
            'iban'=> $this->string(50)->null()->defaultValue(null),
            'adresse'=> $this->string(256)->null()->defaultValue(null),
            'token'=> $this->string(64)->null()->defaultValue(null),
            'authKey'=> $this->string(32)->notNull(),
            'profilePic'=> $this->string(32)->null()->defaultValue(null),
        ], $tableOptions);

        $this->createIndex('authKey','{{%user}}',['authKey'],true);
        $this->createIndex('user_username_uindex','{{%user}}',['username'],true);
        $this->createIndex('profilePic','{{%user}}',['profilePic'],true);
        $this->addForeignKey(
            'fk_domain_belongingRealm',
            '{{%domain}}', 'belongingRealm',
            '{{%realm}}', 'uid',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_gremium_belongingRealm',
            '{{%gremium}}', 'belongingRealm',
            '{{%realm}}', 'uid',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_gremium_parentGremium',
            '{{%gremium}}', 'parentGremium',
            '{{%gremium}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_group_belongingRealm',
            '{{%group}}', 'belongingRealm',
            '{{%realm}}', 'uid',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_group_assertion_group_id',
            '{{%group_assertion}}', 'group_id',
            '{{%group}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_group_assertion_user_id',
            '{{%group_assertion}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_realm_admin_realm_id',
            '{{%realm_admin}}', 'realm_id',
            '{{%realm}}', 'uid',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_realm_admin_user_id',
            '{{%realm_admin}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_realm_assertion_realm_id',
            '{{%realm_assertion}}', 'realm_id',
            '{{%realm}}', 'uid',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_realm_assertion_user_id',
            '{{%realm_assertion}}', 'user_id',
            '{{%user}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            'fk_role_belongingGremium',
            '{{%role}}', 'belongingGremium',
            '{{%gremium}}', 'id',
            'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
            $this->dropForeignKey('fk_domain_belongingRealm', '{{%domain}}');
            $this->dropForeignKey('fk_gremium_belongingRealm', '{{%gremium}}');
            $this->dropForeignKey('fk_gremium_parentGremium', '{{%gremium}}');
            $this->dropForeignKey('fk_group_belongingRealm', '{{%group}}');
            $this->dropForeignKey('fk_group_assertion_group_id', '{{%group_assertion}}');
            $this->dropForeignKey('fk_group_assertion_user_id', '{{%group_assertion}}');
            $this->dropForeignKey('fk_realm_admin_realm_id', '{{%realm_admin}}');
            $this->dropForeignKey('fk_realm_admin_user_id', '{{%realm_admin}}');
            $this->dropForeignKey('fk_realm_assertion_realm_id', '{{%realm_assertion}}');
            $this->dropForeignKey('fk_realm_assertion_user_id', '{{%realm_assertion}}');
            $this->dropForeignKey('fk_role_belongingGremium', '{{%role}}');
            $this->dropTable('{{%domain}}');
            $this->dropTable('{{%gremium}}');
            $this->dropTable('{{%group}}');
            $this->dropPrimaryKey('pk_on_group_assertion','{{%group_assertion}}');
            $this->dropTable('{{%group_assertion}}');
            $this->dropPrimaryKey('pk_on_realm','{{%realm}}');
            $this->dropTable('{{%realm}}');
            $this->dropPrimaryKey('pk_on_realm_admin','{{%realm_admin}}');
            $this->dropTable('{{%realm_admin}}');
            $this->dropPrimaryKey('pk_on_realm_assertion','{{%realm_assertion}}');
            $this->dropTable('{{%realm_assertion}}');
            $this->dropTable('{{%role}}');
            $this->dropTable('{{%user}}');
    }
}
