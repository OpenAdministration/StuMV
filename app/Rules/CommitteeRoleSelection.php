<?php

namespace App\Rules;

use App\Ldap\Committee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates one "{committee_dn}|{role_cn}" pillbox value submitted by
 * App\Livewire\Tools\InviteUser. The DN suffix check has to happen before
 * Committee::find() is ever called with it - Committee has no global scope
 * limiting reads to one realm's own branch (unlike App\Ldap\Community), so
 * find() would otherwise happily resolve a DN belonging to a different
 * realm's committees, or an unrelated LDAP entry entirely, if a tampered
 * value were trusted as-is.
 */
class CommitteeRoleSelection implements ValidationRule
{
    public function __construct(private readonly string $realm_uid) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        [$committeeDn, $roleCn] = array_pad(explode('|', (string) $value, 2), 2, '');

        $committeesRoot = Committee::dnRootResolved($this->realm_uid);

        if ($committeeDn === '' || $roleCn === '' || ! str_ends_with($committeeDn, ','.$committeesRoot)) {
            $fail('tools.invalid_role_selection');

            return;
        }

        $committee = Committee::find($committeeDn);

        if (! $committee || ! $committee->roles()->where('cn', $roleCn)->exists()) {
            $fail('tools.invalid_role_selection');
        }
    }
}
