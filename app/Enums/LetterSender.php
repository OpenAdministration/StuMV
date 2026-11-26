<?php

namespace App\Enums;

/** Whose letterhead, address and contact details a cover letter carries. */
enum LetterSender: string
{
    case Realm = 'realm';
    case User = 'user';
    /** The person the certificate is for. */
    case Member = 'member';

    public function label(): string
    {
        return __('realms.membership_export.sender_'.$this->value);
    }
}
