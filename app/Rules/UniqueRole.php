<?php

namespace App\Rules;

use App\Ldap\Committee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueRole implements ValidationRule
{
    public function __construct(private readonly string $uid, private readonly string $committee_ou) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 'moderators' is reserved for the committee's hidden moderators
        // group (Committee::moderatorsGroup()), which is deliberately
        // excluded from Committee::roles() so it never shows up as a regular
        // role - it has to be reserved here explicitly instead.
        if ($value === 'moderators') {
            $fail(__('validation.unique', ['attribute' => __('Short Name')]));

            return;
        }

        $committee = Committee::findByName($this->uid, $this->committee_ou);
        $exists = $committee->roles()->where('cn', $value)->exists();
        if ($exists) {
            $fail(__('validation.unique', ['attribute' => __('Short Name')]));
        }
    }
}
