<?php

namespace App\Console\Commands\Concerns;

use LdapRecord\Models\Model as LdapModel;

trait DiffsUniqueMembers
{
    /**
     * Diffs a "uniqueMember" attribute against a desired set of member DNs,
     * without printing or writing anything - reused by app/Livewire/SyncLdap.php
     * to preview pending changes, and by SyncsUniqueMembers to apply them.
     * The empty-string placeholder some entries carry (to satisfy the "at
     * least one uniqueMember" schema requirement while otherwise empty) is
     * preserved, never treated as a real member.
     *
     * @param  array<int, string>  $desiredDns
     * @return array{survivors: array<int, string>, additions: array<int, string>, removals: array<int, string>, hasPlaceholder: bool}
     */
    protected function diffUniqueMembers(LdapModel $entity, array $desiredDns): array
    {
        $current = $entity->getAttribute('uniqueMember') ?? [];
        $desiredDns = array_values(array_unique($desiredDns));

        $hasPlaceholder = in_array('', $current, true);
        $currentRealMembers = array_values(array_diff($current, ['']));

        return [
            'survivors' => array_values(array_intersect($currentRealMembers, $desiredDns)),
            'additions' => array_values(array_diff($desiredDns, $currentRealMembers)),
            'removals' => array_values(array_diff($currentRealMembers, $desiredDns)),
            'hasPlaceholder' => $hasPlaceholder,
        ];
    }

    /**
     * @param  array{survivors: array<int, string>, additions: array<int, string>, hasPlaceholder: bool}  $diff
     */
    protected function applyUniqueMembersDiff(LdapModel $entity, array $diff): void
    {
        $final = [...$diff['survivors'], ...$diff['additions']];
        if ($diff['hasPlaceholder'] || empty($final)) {
            $final[] = '';
        }

        $entity->replaceAttribute('uniqueMember', $final);
    }
}
