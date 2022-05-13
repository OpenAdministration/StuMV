<?php

namespace app\models\db\search;

use app\models\db\Group;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * GroupSearch represents the model behind the search form of `app\models\Groups`.
 */
class GroupSearch extends Group
{
    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['id'], 'integer'],
            [['name', 'realm_uid'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
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
    public function search($params)
    {
        $query = Group::find();

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
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'realm_uid', $this->realm_uid]);

        return $dataProvider;
    }
}
