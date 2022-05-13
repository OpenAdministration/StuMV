<?php

namespace app\models\db;

use yii\db\ActiveQuery;

/**
 * This is the model class for table "gremien".
 *
 * @property int $id
 * @property string $name
 * @property int $parent_gremium_id
 * @property int $realm_uid
 *
 * @property-read Realm $realm
 * @property-read Gremium $parentGremium
 * @property-read Gremium[] $childGremien
 * @property-read Role[] $roles
 */
class Gremium extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName() : string
    {
        return 'gremium';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'realm_uid'], 'required'],
            [['id', 'parent_gremium_id'], 'integer'],
            [['name'], 'string', 'max' => 128],
            [['realm_uid'], 'string', 'max' => 32],
            [['id'], 'unique'],
            [['parent_gremium_id'], 'exist', 'skipOnError' => true, 'targetClass' => __CLASS__, 'targetAttribute' => ['parent_gremium_id' => 'id']],
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
            'parent_gremium_id' => 'Übergeordnetes Gremium',
        ];
    }

    /**
     * Gets query for [[parentGremium]].
     *
     * @return ActiveQuery
     */
    public function getParentGremium(): ActiveQuery
    {
        return $this->hasOne(__CLASS__, ['id' => 'parent_gremium_id']);
    }

    /**
     * Gets query for [[Gremien]].
     *
     * @return ActiveQuery
     */
    public function getChildGremien(): ActiveQuery
    {
        return $this->hasMany(__CLASS__, ['parent_gremium_id' => 'id']);
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

    /**
     * Gets query for [[Roles]].
     *
     * @return ActiveQuery
     */
    public function getRoles() : ActiveQuery
    {
        return $this->hasMany(Role::class, ['gremium_id' => 'id']);
    }

}
