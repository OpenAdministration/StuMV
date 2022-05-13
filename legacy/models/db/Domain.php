<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "domains".
 *
 * @property string|null $name
 * @property int|null $activeMail
 * @property string|null $realm_uid
 * @property int|null $forRegistration
 * @property-read Realm $realm
 * @property int $id [int(11)]
 *
 */

class Domain extends ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'domain';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name'], 'string', 'max' => 128],
            [['name'], 'unique'],
            [['activeMail', 'forRegistration'], 'boolean'],
            [['realm_uid'], 'string', 'max' => 32, 'min' => 1],
            [['realm_uid'], 'exist', 'skipOnError' => true, 'targetClass' => Realm::class, 'targetAttribute' => ['realm_uid' => 'uid']],
            [['forRegistration'], 'boolean'],
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
            'activeMail' => 'Stelle Mails bereit',
            'realm_uid' => 'Zugehöriges Realm',
            'forRegistration' => 'Zur Registrierung',
        ];
    }

    /**
     * Gets query for [[Realm]].
     *
     * @return ActiveQuery
     */
    public function getRealm() : ActiveQuery
    {
        return $this->hasOne(Realm::class, ['uid' => 'realm_uid']);
    }
}
