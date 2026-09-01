<?php

namespace App\Support;

use App\Ldap\Community;
use App\Models\Invitation;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class InvitationMailer
{
    public function send(Invitation $invitation, Community $community): void
    {
        $url = URL::temporarySignedRoute('invitation.accept', $invitation->expires_at, [
            'realm' => $community->getShortCode(),
            'invitation' => $invitation->id,
            'hash' => sha1($invitation->email),
        ]);

        // Deferred - QUEUE_CONNECTION is "sync" with no worker.
        dispatch(function () use ($invitation, $url, $community): void {
            Notification::route('mail', $invitation->email)
                ->notify(new UserInvitation($invitation, $url, $community->getLongName()));
        })->afterResponse();
    }
}
