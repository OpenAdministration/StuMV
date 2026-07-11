<?php

namespace App\Rules;

use App\Ldap\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $mail_taken = User::findBy('mail', $value)?->exists();
        if ($mail_taken) {
            $fail(__('user.error.email_in_use'));
        }
    }
}
