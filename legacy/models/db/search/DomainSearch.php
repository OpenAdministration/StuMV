<?php

namespace app\models\db\search;

use app\models\db\Domain;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * DomainSearch represents the model behind the search form of `app\models\Domains`.
 */
class DomainSearch extends Domain
{
    /**
     * {@inheritdoc}
     */
    public function rules() : array
    {
        return [
            [['id', 'activeMail', 'forRegistration'], 'integer'],
            [['realm_uid', 'name'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
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
        $query = Domain::find();

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
            'activeMail' => $this->activeMail,
            'forRegistration' => $this->forRegistration,
        ]);

        $query->andFilterWhere(['like', 'realm_uid', $this->realm_uid])
            ->andFilterWhere(['like', 'name', $this->name]);



        return $dataProvider;
    }
}
