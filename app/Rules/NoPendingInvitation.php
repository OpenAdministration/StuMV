<?php

namespace App\Rules;

use App\Models\Invitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NoPendingInvitation implements ValidationRule
{
    public function __construct(private readonly string $realm) {}

    /**
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Invitation::where('realm', $this->realm)
            ->where('email', $value)
            ->whereNull('accepted_at')
            ->exists();

        if ($exists) {
            $fail(__('tools.pending_invitation_exists'));
        }
    }
}
