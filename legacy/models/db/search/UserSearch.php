<?php

namespace app\models\db\search;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\db\User;

/**
 * UserSearch represents the model behind the search form of `app\models\db\User`.
 */
class UserSearch extends User
{
    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['id', 'status'], 'integer'],
            [['fullName', 'username', 'email', 'password', 'phone', 'iban', 'adresse', 'token', 'authKey', 'profilePic'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios() : array
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search(array $params) : ActiveDataProvider
    {
        $query = User::find();
        $identity = Yii::$app->user->identity;
        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'status' => $this->status,
        ]);

        if(!$identity->isSuperAdmin()){
            $query->innerJoin('realm_assertion', 'realm_assertion.user_id = user.id');
            $query->andWhere(['realm_uid' => $identity->realmUids]);
        }

        $query->andFilterWhere(['like', 'fullName', $this->fullName])
            ->andFilterWhere(['like', 'username', $this->username])
            ->andFilterWhere(['like', 'email', $this->email]);


        return $dataProvider;
    }
}
