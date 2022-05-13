<?php


namespace app\commands;


use app\models\db\Group;
use app\models\db\Realm;
use yii\base\InvalidArgumentException;

class FuiController extends \yii\console\Controller
{
    public function actionNew($realm)
    {
        $groups = [
            'ref-finanzen',
            'ref-finanzen-hv',
            'ref-finanzen-kv',
            'ref-finanzen-belege',
            'stura',
        ];

        if(Realm::findOne($realm) === null){
            throw new InvalidArgumentException("Unknown Realm");
        }

        foreach ($groups as $group){
            $g = new Group();
            $g->name = $realm . "-" . $group;
            $g->realm_uid = $realm;
            $g->save();
        }
    }
}