<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "realm_assertions".
 *
 * @property string|null $user_id
 * @property string|null $realm_uid
 *
 * @property-read User $user
 * @property-read Realm $realm
 */
class RealmAssertion extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'realm_assertion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['user_id', 'realm_uid'], 'required'],
            [['user_id'], 'integer'],
            [['realm_uid'], 'string', 'max' => 16],
            [['user_id', 'realm_uid'], 'unique', 'targetAttribute' => ['user_id', 'realm_uid'],
                'message' => 'Diese*r Nutzer*in ist bereits in diesem Realm'
            ],
            [['realm_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Realm::class, 'targetAttribute' => ['realm_uid' => 'uid']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'user_id' => 'User ID',
            'realm_uid' => 'Realm ID',
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

    /**
     * @return ActiveQuery
     */
    public function getUser() : ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

}
