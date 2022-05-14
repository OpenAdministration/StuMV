<?php

namespace app\models\db\search;

use app\models\db\Realm;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * RealmSearch represents the model behind the search form of `app\models\Realms`.
 */
class RealmSearch extends Realm
{
    public function behaviors() : array
    {
        return parent::behaviors(); // TODO: Change the autogenerated stub
    }

    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['uid', 'long_name'], 'safe'],
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
        $query = Realm::find();

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
        $query->andFilterWhere(['like', 'uid', $this->uid])
            ->andFilterWhere(['like', 'long_name', $this->long_name]);


        return $dataProvider;
    }


}