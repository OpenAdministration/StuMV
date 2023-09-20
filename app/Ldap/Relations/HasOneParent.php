<?php

namespace App\Ldap\Relations;

use Illuminate\Support\Str;
use LdapRecord\Models\Relations\Relation;

class HasOneParent extends Relation {

    public function getResults(): \LdapRecord\Models\Collection
    {
        $dn =  Str::after($this->parent->getDn(), ',');
        $model = $this->getQuery()->setDn($dn)->first();
        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }
}
