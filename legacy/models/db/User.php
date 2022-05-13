<?php

namespace app\models\db;

use app\models\validators\MailDomainRegistrationValidator;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "users".
 *
 * @property int $id
 * @property string|null $fullName
 * @property string|null $username
 * @property string|null $email
 * @property int|null $status
 * @property string|null $password
 * @property string|null $phone
 * @property string|null $iban
 * @property string|null $authKey
 * @property string|null $adresse
 * @property string $nameGiven
 * @property string $nameFamily
 * @property string $organization
 * @property string $organizationIds
 * @property string $token [varchar(64)]
 * @property string $profilePic [varchar(32)]
 * @property-read Realm[] $adminRealms
 * @property-read Realm[] $realms
 * @property-read string[] $realmUids
 */
class User extends ActiveRecord implements IdentityInterface
{

    public const STAUTS_UNVERIVIED = 0;
    public const STAUTS_VERIVIED = 1;
    public const STAUTS_BLOCKED = 2;

    public const SCENARIO_REGISTER = 'register';
    public const SCENARIO_PROFILE = 'profile';

    public $password_repeat;
    public $imageFile;
    /**
     * @var mixed|null
     */

    public function scenarios(): array
    {
        // only safe attributes can be loaded with load()
        return [
            self::SCENARIO_DEFAULT => [],
            self::SCENARIO_REGISTER => ['username', 'password', 'password_repeat', 'email', ],
            self::SCENARIO_PROFILE => ['fullName', 'phone', 'iban', 'adresse'],
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['fullName', 'username', 'email', 'phone', 'iban', 'adresse'], 'trim'],
            [['status'], 'integer'],
            [['fullName', 'email'], 'string', 'max' => 64],
            ['email', 'email'],
            ['email', MailDomainRegistrationValidator::class],
            ['username', 'match', 'pattern' => '/^[a-z]\w*$/i'],
            [['username', 'phone'], 'string', 'max' => 32, 'min' => 2],
            [['iban'], 'string', 'max' => 50],
            [['adresse'], 'string', 'max' => 256],
            [['username',], 'unique',
                'targetAttribute' => ['username'],
                'message' => 'Der Nutzer*innenname wird bereits verwendet',
            ],
            [['password', 'password_repeat'], 'string', 'max' => 128, 'min' => 10, 'on' => self::SCENARIO_REGISTER],
            ['password_repeat', 'compare',
                'compareAttribute' => 'password',
                'message' => 'Passwörter müssen identisch sein',
                'on' => self::SCENARIO_REGISTER,
            ],
            [['username', 'password', 'password_repeat', 'email', ], 'required', 'on' => self::SCENARIO_REGISTER],
            //['imageFile', 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg']
        ];
    }

    public static function tableName() : string
    {
        return 'user';
    }

    /**
     * @inheritDoc
     */
    public static function findIdentity($id) : ?self
    {
        if(is_numeric($id)){
            return self::findOne(['id' => $id]);
        }

        return self::findOne(['username' => $id]);
    }

    /**
     * @inheritDoc
     */
    public static function findIdentityByAccessToken($token, $type = null) : ?self
    {
        Yii::debug('SEARCH AUTHKEY: ' . $token);
        return self::findOne(['authKey' => $token]);
    }


    public static function findIdentityByUsername(string $username) : ?self
    {
        Yii::debug('SEARCH USERNAME: ' . $username);
        return self::findOne(['username' => $username]);
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @inheritDoc
     */
    public function getAuthKey() : ?string
    {
        // TODO: Implement getAuthKey() method.
        return $this->authKey;
    }

    /**
     * @inheritDoc
     */
    public function validateAuthKey($authKey) : ?bool
    {
        return $this->authKey === $authKey;
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'E-Mail Adresse',
            'username' => 'Nutzer*innenname',
            'fullName' => 'Vollständiger Name',
            'password' => 'Passwort',
            'password_repeat' => 'Passwort wiederholen',
            'iban' => 'IBAN',
            'phone' => 'Telefonischer Kontakt',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'email' => 'Bitte verwende deine Uni-Mail Adresse',
            'username' => 'darf noch nicht bereits vergeben sein, muss mit einem Buchstaben beginnen und darf sonst nur Zahlen, Buchstaben oder Unterstriche enthalten',
        ];
    }

    /**
     * Gets query for [[GroupAssertions]].
     *
     * @return ActiveQuery
     */
    public function getGroupAssertions()
    {
        return $this->hasMany(GroupAssertion::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Groups]].
     *
     * @return ActiveQuery
     */
    public function getGroups(): ActiveQuery
    {
        return $this->hasMany(Group::class, ['id' => 'group_id'])->viaTable('group_assertion', ['user_id' => 'id']);
    }


    public function getAdminRealmAssertions() : ActiveQuery
    {
        return $this->hasMany(RealmAdmin::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Realms]] where User is Admin.
     *
     * @return ActiveQuery
     */
    public function getAdminRealms() : ActiveQuery
    {
        return $this->hasMany(Realm::class, ['uid' => 'realm_uid'])->viaTable('realm_admin', ['user_id' => 'id']);
    }

    /**
     * Gets query for [[RealmAssertions]].
     *
     * @return ActiveQuery
     */
    public function getRealmAssertions() : ActiveQuery
    {
        return $this->hasMany(RealmAssertion::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for asserted [[Realms]].
     *
     * @return ActiveQuery
     */
    public function getRealms() : ActiveQuery
    {
        return $this->hasMany(Realm::class, ['uid' => 'realm_uid'])->viaTable('realm_assertion', ['user_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRealmUids() : ActiveQuery
    {
        return $this->hasMany(RealmAssertion::class, ['user_id' => 'id'])->select('realm_uid')->asArray();
    }

    /**
     * Gets query for [[RoleAssertions]].
     *
     * @return ActiveQuery
     */
    public function getActiveRoleAssertions() : ActiveQuery
    {
        return $this->hasMany(RoleAssertion::class, ['user_id' => 'id'])->where(['role_assertion.from <= NOW()']);
    }

    /**
     * Gets query for [[RoleAssertions]].
     *
     * @return ActiveQuery
     */
    public function getAllRoleAssertions() : ActiveQuery
    {
        return $this->hasMany(RoleAssertion::class, ['user_id' => 'id']);
    }

    /**
     * Gets query for [[Roles]].
     *
     * @return ActiveQuery
     */
    public function getRoles() : ActiveQuery
    {
        return $this->hasMany(Role::class, ['id' => 'role_id'])->viaTable('role_assertion', ['user_id' => 'id']);
    }

    public function isSuperAdmin() : bool
    {
        return $this->getAdminRealmAssertions()->where(['realm_uid' => 'oa'])->exists();
    }

    public function isRealmAdmin(string $realmUid) : bool
    {
        if($this->isSuperAdmin()){
            // short circuit if realm admin
            return true;
        }
        //might be better cached if traversed in php not in sql
        return $this->getAdminRealmAssertions()->where(['realm_uid' => $realmUid])->exists();
    }

    public function isRealmMember(string $realmUid) : bool
    {
        if($this->isRealmAdmin($realmUid)){
            // short circuit if realm admin
            return true;
        }
        //might be better cached if traversed in php not in sql
        return $this->getRealmAssertions()->where(['realm_uid' => $realmUid, 'user_id' => $this->id])->exists();
    }

}
