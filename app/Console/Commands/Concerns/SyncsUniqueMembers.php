<?php

namespace App\Console\Commands\Concerns;

use LdapRecord\Models\Model as LdapModel;

trait SyncsUniqueMembers
{
    use DiffsUniqueMembers;

    /**
     * Reconciles a "uniqueMember" attribute with a desired set of member DNs
     * in a single LDAP replace call (instead of one add/remove call per
     * changed member). Members already present keep their relative order and
     * are left untouched; the entry isn't written to at all if nothing
     * changed.
     *
     * @param  array<int, string>  $desiredDns
     * @param  string  $prefix  The tree-drawing prefix (e.g. "  |   |-> ") for
     *                          the Add/Remove lines, matching the caller's
     *                          nesting depth.
     */
    protected function syncUniqueMembers(LdapModel $entity, array $desiredDns, string $prefix): void
    {
        $diff = $this->diffUniqueMembers($entity, $desiredDns);

        if (empty($diff['additions']) && empty($diff['removals'])) {
            return;
        }

        foreach ($diff['removals'] as $removed) {
            $this->line("{$prefix}<fg=red>Remove: $removed</>");
        }
        foreach ($diff['additions'] as $added) {
            $this->line("{$prefix}<fg=green>Add: $added</>");
        }

        $this->applyUniqueMembersDiff($entity, $diff);
    }
}
