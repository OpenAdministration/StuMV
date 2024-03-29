<?php

namespace App\Rules;

use App\Ldap\Domain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DomainRegistrationRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Domain::findBy('dc', $value)?->exists();
        if(!$exists){
            $fail('domain.domain_unknown_for_registration')->translate(['domain' => $value]);
        }
    }
}
