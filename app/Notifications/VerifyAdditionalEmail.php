<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyAdditionalEmail extends Notification
{
    public function __construct(
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
            ->subject(__('profile.emails_mail_subject', ['community' => $this->communityName]))
            ->line(__('profile.emails_mail_line_between_greeting_and_action', ['community' => $this->communityName]))
            ->action(__('profile.emails_mail_button_action'), $this->url)
            ->line(__('profile.emails_mail_ignore_notice'));
    }
}
