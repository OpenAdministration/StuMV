<?php

namespace App\Ldap\Relations;

use LdapRecord\Models\Collection;
use Illuminate\Support\Str;
use LdapRecord\Models\Relations\Relation;

class HasOneParent extends Relation {

    public function getResults(): Collection
    {
        $dn =  Str::after($this->parent->getDn(), ',');
        $model = $this->getQuery()->setDn($dn)->first();
        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }
}
