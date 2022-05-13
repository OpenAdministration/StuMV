<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "roles".
 *
 * @property int $id
 * @property string|null $name
 * @property Gremium $gremium_id
 *
 * @property-read RoleAssertion $roleAssertions
 * @property-read Gremium $gremium
 * @property User[] $assertedUsers
 */
class Role extends ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName() : string
    {
        return 'role';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['gremium_id'], 'required'],
            [['id', 'gremium_id'], 'integer'],
            [['name'], 'string', 'max' => 64, 'min' => 2],
            [['id'], 'unique'],
            [['gremium_id'], 'exist', 'skipOnError' => true, 'targetClass' => Gremium::class, 'targetAttribute' => ['gremium_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'gremium_id' => 'Zugehöriges Gremium',
        ];
    }

    /**
     * Gets query for [[Gremium]].
     *
     * @return ActiveQuery
     */
    public function getGremium() : ActiveQuery
    {
        return $this->hasOne(Gremium::class, ['id' => 'gremium_id']);
    }

    public function getRoleAssertions() : ActiveQuery
    {
        return $this->hasMany(RoleAssertion::class, ['role_id' => 'id']);
    }

    public function getAssertedUsers() : ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->via('roleAssertions');
    }
}
