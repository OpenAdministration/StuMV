<?php

namespace App\Rules;

use App\Ldap\Community;
use App\Ldap\Domain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Registration is realm-bound now (one specific community, chosen via the
 * {realm}/register URL) - the email's domain must belong to that same
 * community, not just any community anywhere in the directory.
 */
class DomainRegistrationRule implements ValidationRule
{
    public function __construct(private readonly Community $realm) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Domain::fromCommunity($this->realm->getShortCode())->where('dc', $value)->exists();
        if (! $exists) {
            $fail('domain.domain_unknown_for_registration')->translate(['domain' => $value]);
        }
    }
}
