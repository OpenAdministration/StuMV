<?php

namespace App\Rules;

use App\Ldap\Community;
use App\Ldap\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueEmail implements ValidationRule
{
    /**
     * Scoped to a single realm - the same email can now legitimately belong
     * to independent accounts in different realms (each account is clearly
     * assigned to one realm), so uniqueness is only meaningful within one.
     */
    public function __construct(private readonly ?Community $realm = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // No realm resolved yet (e.g. the domain field itself is still
        // invalid) - that error takes precedence, nothing useful to scope to.
        if (! $this->realm) {
            return;
        }

        $mailTaken = User::query()->in($this->realm->peopleDn())->where('mail', '=', $value)->exists();

        if ($mailTaken) {
            $fail(__('user.error.email_in_use'));
        }
    }
}
