<?php

namespace app\models\db;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "realm".
 *
 * @property string|null $uid
 * @property string|null $long_name
 *
 * @property-read Domain[] $domains
 * @property-read Gremium[] $gremien
 * @property-read User[] $assertedUsers
 * @property-read RealmAssertion $realmAssertions
 * @property-read Group[] $groups
 */
class Realm extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'realm';
    }


    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['uid'], 'string', 'max' => 32],
            [['long_name'], 'string', 'max' => 128],
            [['uid'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() : array
    {
        return [
            'uid' => 'Kürzel',
            'long_name' => 'Vollständiger Name',
        ];
    }

    /**
     * Gets query for [[Domains]].
     *
     * @return ActiveQuery
     */
    public function getDomains() : ActiveQuery
    {
        return $this->hasMany(Domain::class, ['realmUid' => 'uid']);
    }

    /**
     * Gets query for [[Gremiens]].
     *
     * @return ActiveQuery
     */
    public function getGremien(): ActiveQuery
    {
        return $this->hasMany(Gremium::class, ['realm_uid' => 'uid']);
    }

    /**
     * Gets query for [[Groups]].
     *
     * @return ActiveQuery
     */
    public function getGroups(): ActiveQuery
    {
        return $this->hasMany(Group::class, ['realm_uid' => 'uid']);
    }

    /**
     * Gets query for [[RealmAssertions]].
     *
     * @return ActiveQuery
     */
    public function getRealmAssertions(): ActiveQuery
    {
        return $this->hasMany(RealmAssertion::class, ['realm_uid' => 'uid']);
    }

    public function getAssertedUsers() : ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->via('realmAssertions');
    }

    public function getRealmAdminAssertions(): ActiveQuery
    {
        return $this->hasMany(RealmAdmin::class, ['realm_uid' => 'uid']);
    }

    public function getAdminUsers() : ActiveQuery
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])->via('realmAdminAssertions');
    }

}
