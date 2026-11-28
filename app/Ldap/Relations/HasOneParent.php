<?php

namespace App\Ldap\Relations;

use App\Ldap\Dn;
use Illuminate\Support\Str;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Relations\Relation;

class HasOneParent extends Relation
{
    public function getResults(): Collection
    {
        $dn = Str::after(Dn::of($this->parent), ',');
        $model = $this->getQuery()->setDn($dn)->first();

        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }
}
