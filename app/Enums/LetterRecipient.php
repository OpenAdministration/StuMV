<?php

namespace App\Enums;

/** Who a cover letter is addressed to. */
enum LetterRecipient: string
{
    /** The person whose memberships are exported, at their profile address. */
    case Member = 'member';
    case Other = 'other';

    public function label(): string
    {
        return __('realms.membership_export.recipient_'.$this->value);
    }
}
