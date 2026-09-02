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
    /**
     * $ignoreUsername exempts one account's own entry, so re-saving addresses
     * it already holds doesn't collide with itself. "mail" is multi-valued
     * (see App\Ldap\User), so this covers primary and additional addresses
     * alike, in both directions.
     */
    public function __construct(
        private readonly ?Community $realm = null,
        private readonly ?string $ignoreUsername = null,
    ) {}

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

        $holder = User::query()->in($this->realm->peopleDn())->where('mail', '=', $value)->first();

        if ($holder && $holder->getFirstAttribute('uid') !== $this->ignoreUsername) {
            $fail(__('user.error.email_in_use'));
        }
    }
}
