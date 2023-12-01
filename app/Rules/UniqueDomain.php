<?php

namespace App\Rules;

use App\Ldap\Domain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // probably has to be superadmin, otherwise other domains might not be visable
        $exists = Domain::query()->findBy('dc', $value)?->exists();
        if($exists){
            $fail('validation.unique')->translate(['attribute' => $attribute]);
        }
    }
}
