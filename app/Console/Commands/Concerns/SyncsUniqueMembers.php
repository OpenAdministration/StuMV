<?php

namespace App\Console\Commands\Concerns;

use LdapRecord\Models\Model as LdapModel;

trait SyncsUniqueMembers
{
    /**
     * Reconciles a "uniqueMember" attribute with a desired set of member DNs
     * in a single LDAP replace call (instead of one add/remove call per
     * changed member). Members already present keep their relative order and
     * are left untouched; the entry isn't written to at all if nothing
     * changed. The empty-string placeholder some entries carry (to satisfy
     * the "at least one uniqueMember" schema requirement while otherwise
     * empty) is preserved, never treated as a real member.
     *
     * @param  array<int, string>  $desiredDns
     */
    protected function syncUniqueMembers(LdapModel $entity, array $desiredDns): void
    {
        $current = $entity->getAttribute('uniqueMember') ?? [];
        $desiredDns = array_values(array_unique($desiredDns));

        $hasPlaceholder = in_array('', $current, true);
        $currentRealMembers = array_values(array_diff($current, ['']));

        $survivors = array_values(array_intersect($currentRealMembers, $desiredDns));
        $additions = array_values(array_diff($desiredDns, $currentRealMembers));
        $removals = array_values(array_diff($currentRealMembers, $desiredDns));

        if (empty($additions) && empty($removals)) {
            return;
        }

        foreach ($removals as $removed) {
            $this->comment("  |  |  |-> Remove: $removed");
        }
        foreach ($additions as $added) {
            $this->comment("  |  |  |-> Add: $added");
        }

        $final = [...$survivors, ...$additions];
        if ($hasPlaceholder || empty($final)) {
            $final[] = '';
        }

        $entity->replaceAttribute('uniqueMember', $final);
    }
}
