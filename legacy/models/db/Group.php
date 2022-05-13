<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "groups".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $realm_uid
 *
 * @property-read Realm $realm
 */
class Group extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'group';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['id', 'integer'],
            [['name'], 'string', 'max' => 64],
            [['realm_uid'], 'string', 'max' => 32],
            [['realm_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Realm::class, 'targetAttribute' => ['realm_uid' => 'uid']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'realm_uid' => 'Zugehöriger Realm',
        ];
    }

    /**
     * Gets query for [[Realm]].
     *
     * @return ActiveQuery
     */
    public function getRealm(): ActiveQuery
    {
        return $this->hasOne(Realm::class, ['uid' => 'realm_uid']);
    }

    public function getRoles() : ActiveQuery
    {
        return $this->hasMany(Role::class, ['id' => 'role_id'])->viaTable('group_assertion', ['group_id' => 'id']);
    }

    public function getUsers() : ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->viaTable('role_assertion', ['role_id' => 'id'])->via('roles');
    }
}
