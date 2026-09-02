<?php

namespace App\Support;

use App\Ldap\Community;
use App\Models\UserAdditionalEmail;
use App\Notifications\VerifyAdditionalEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class AdditionalEmailMailer
{
    /** How long a verification link stays valid. */
    private const int LINK_LIFETIME_HOURS = 24;

    /** Reading the mailbox is the proof, so the link needs no login. */
    public function sendVerification(UserAdditionalEmail $additionalEmail, Community $community): void
    {
        $url = URL::temporarySignedRoute('profile.emails.verify', now()->addHours(self::LINK_LIFETIME_HOURS), [
            'realm' => $community->getShortCode(),
            'additionalEmail' => $additionalEmail->id,
            'hash' => sha1($additionalEmail->address),
        ]);

        // Deferred - QUEUE_CONNECTION is "sync" with no worker.
        dispatch(function () use ($additionalEmail, $url, $community): void {
            Notification::route('mail', $additionalEmail->address)
                ->notify(new VerifyAdditionalEmail($url, $community->getLongName()));
        })->afterResponse();
    }
}
