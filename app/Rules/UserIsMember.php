<?php

namespace App\Rules;

use App\Ldap\Community;
use App\Ldap\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserIsMember implements ValidationRule
{
    private Community $community;
    public function __construct(string $community_name){
        $this->community = Community::findByUid($community_name);
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(!($value instanceof User)){
            $value = User::findByUsername($value);
        }
        if(!empty($value) && $this->community->membersGroup()->members()->exists($value) !== true){
            $fail('realms.user_is_no_member');
        }
    }
}
