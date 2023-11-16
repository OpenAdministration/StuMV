<?php

namespace App\Rules;

use App\Ldap\Committee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueRole implements ValidationRule
{
    public function __construct(private string $uid,private string $committee_ou){
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $committee = Committee::findByName($this->uid, $this->committee_ou);
        $exists = $committee->roles()->where('cn', $value)->exists();
        if ($exists){
            $fail(__('validation.unique', ['attribute' => __('Short Name')]));
        }
    }
}
