<?php

namespace App\Ldap\Relations;

use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\Relation;

class HasOneChild extends Relation {

    public function getResults(): \LdapRecord\Models\Collection
    {
        $dn = $this->relationKey . ',' . $this->parent->getDn();
        $model = $this->getQuery()->setDn($dn)->first();
        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }
}
