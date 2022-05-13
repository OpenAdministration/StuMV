<?php

namespace app\models\db;

use yii\db\ActiveQuery;

/**
 * This is the model class for table "group_assertions".
 *
 * @property int|null $role_id
 * @property int|null $group_id
 *
 * @property Group $group
 */
class GroupAssertion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'group_assertion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['group_id'], 'integer'],
            [['role_id'], 'integer'],
            [['group_id'], 'exist', 'skipOnError' => true, 'targetClass' => Group::class, 'targetAttribute' => ['group_id' => 'id']],
            [['role_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['role_id' => 'id']],
            [['group_id', 'role_id'], 'unique',
                'targetAttribute' => ['group_id', 'role_id'],
                'message' => 'Diese Role besitzt diese Gruppe bereits',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'role_id' => 'Rolle',
            'group_id' => 'Gruppe',
        ];
    }

    /**
     * Gets query for [[Group]].
     *
     * @return ActiveQuery
     */
    public function getGroup() : ActiveQuery
    {
        return $this->hasOne(Group::class, ['id' => 'group_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getRoles()
    {
        return $this->hasOne(Role::class, ['id' => 'role_id']);
    }
}
