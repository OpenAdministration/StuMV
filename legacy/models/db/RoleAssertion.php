<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "role_assertions".
 *
 * @property string|null $user_id
 * @property int|null $role_id
 *
 * @property-read ActiveQuery $user
 * @property Role $role
 * @property string $from [date]
 * @property string $until [date]
 */
class RoleAssertion extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'role_assertion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['role_id'], 'integer'],
            [['user_id'], 'integer'],
            [['from', 'until'], 'date', 'format' => 'Y-m-d'],
            [['from', 'role_id', 'user_id'], 'required'],
            [['role_id'], 'exist', 'skipOnError' => true, 'targetClass' => Role::class, 'targetAttribute' => ['role_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['role_id'], 'unique',
                'targetAttribute' => ['role_id', 'user_id'],
                'message' => 'Nutzer:in besitzt diese Rolle bereits',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'user_id' => 'Nutzer',
            'role_id' => 'Rolle',
            'from' => 'Zugeordnet ab',
            'until' => 'Zugeordnet bis',
        ];
    }

    /**
     * Gets query for [[Group]].
     *
     * @return ActiveQuery
     */
    public function getRole(): ActiveQuery
    {
        return $this->hasOne(Role::class, ['id' => 'role_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser() : ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
