<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/** @var $this View */
/** @var $invitedByFullName string */
/** @var $invitedByMail string */
/** @var $registrationUrl string */

?>
    Hi,

    du wurdest von <?= htmlspecialchars($invitedByFullName) ?> (<?= htmlspecialchars($invitedByMail) ?>) eingeladen einen Open-Administration Account zu erstellen.

    Unter folgendem Link kannst du dich registrieren:

    <?= Html::a($registrationUrl, $registrationUrl) ?>



