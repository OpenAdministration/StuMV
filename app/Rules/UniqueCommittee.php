<?php

namespace App\Rules;

use App\Ldap\Committee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueCommittee implements ValidationRule
{
    public function __construct(private $realm_uid) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $notUnique = Committee::fromCommunity($this->realm_uid)->where('ou', $value)->exists();
        if ($notUnique) {
            $fail('validation.unique')->translate(['attribute' => __('Short Name')]);
        }
    }
}
