<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/** @var $this View */
/** @var $token string */
/** @var $username string */

$url = Url::to(['auth/confirm', 'token' => $token], true);

?>
Hi,

mit deiner Mail Adresse wurde ein neuer Open-Administration Account erstellt:

    Nutzername: <?= $username . PHP_EOL ?>
    <?= Html::a('Aktivierungslink', $url) ?>


Falls der Aktivierungslink nicht richtig angezeigt wird, kopiere den folgenden Link in deinen Browser:

<?= $url ?>