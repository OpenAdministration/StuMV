<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $url,
        private readonly string $communityName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('invitations.mail_subject', ['community' => $this->communityName]))
            ->line(__('invitations.mail_line_between_greeting_and_action', ['community' => $this->communityName]))
            ->action(__('invitations.mail_button_action'), $this->url)
            ->line(__('invitations.mail_expire_notice', ['date' => $this->invitation->expires_at->translatedFormat('d.m.Y')]));
    }
}
