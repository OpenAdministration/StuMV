<?php

namespace App\Rules;

use App\Ldap\Community;
use App\Ldap\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UserIsMember implements ValidationRule
{
    private readonly Community $community;

    public function __construct(string $community_name)
    {
        $this->community = Community::findByUid($community_name);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof User) {
            // Already-resolved entry: verify it actually lives under this
            // community's People branch rather than trusting the caller.
            $isMember = str_ends_with((string) $value->getDn(), ','.$this->community->peopleDn());
        } else {
            // Resolve directly within this community's own People branch -
            // scoped by construction, so there's no risk of resolving an
            // unrelated same-username account from a different realm.
            $isMember = User::query()->in($this->community->peopleDn())->where('uid', '=', $value)->exists();
        }

        if (! $isMember) {
            $fail('realms.user_is_no_member');
        }
    }
}
