<?php

namespace App\Ldap\Traits;

use App\Ldap\Relations\HasOneChild;
use App\Ldap\Relations\HasOneParent;

trait HasRelationships
{
    /** @param array<int, class-string>|class-string $related */
    public function hasOneChild(array|string $related, string $childDnPrefix): HasOneChild
    {
        return new HasOneChild($this->newQuery(), $this, $related, $childDnPrefix, 'dn');
    }

    /** @param array<int, class-string>|class-string $related */
    public function hasOneParent(array|string $related): HasOneParent
    {
        return new HasOneParent($this->newQuery(), $this, $related, 'dn', 'dn');
    }
}
