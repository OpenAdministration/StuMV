<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Asks the owner of an inactive account to log in if they want to keep it. */
class InactiveAccountNotice extends Notification
{
    public function __construct(
        private readonly string $name,
        private readonly string $username,
        private readonly string $realmName,
        private readonly string $loginUrl,
        private readonly ?CarbonInterface $lastLogin,
        private readonly CarbonInterface $deleteAfter,
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
        $replace = ['username' => $this->username, 'realm' => $this->realmName];

        return (new MailMessage)
            ->subject(__('accounts.inactive_mail_subject', ['realm' => $this->realmName]))
            ->greeting(__('accounts.inactive_mail_greeting', ['name' => $this->name]))
            ->line($this->lastLogin
                ? __('accounts.inactive_mail_last_login', [...$replace, 'date' => $this->lastLogin->format('d.m.Y')])
                : __('accounts.inactive_mail_never_logged_in', $replace))
            ->line(__('accounts.inactive_mail_deadline', ['date' => $this->deleteAfter->format('d.m.Y')]))
            ->action(__('accounts.inactive_mail_action'), $this->loginUrl)
            ->line(__('accounts.inactive_mail_nothing_to_do'));
    }
}
