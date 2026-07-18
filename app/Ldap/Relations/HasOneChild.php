<?php

namespace App\Ldap\Relations;

use LdapRecord\Models\Collection;
use LdapRecord\Models\Relations\Relation;

class HasOneChild extends Relation
{
    public function getResults(): Collection
    {
        $dn = $this->relationKey.','.$this->parent->getDn();
        $model = $this->getQuery()->setDn($dn)->first();

        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }
}
