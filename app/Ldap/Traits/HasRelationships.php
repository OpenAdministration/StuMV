<?php

namespace App\Ldap\Traits;
use App\Ldap\AdminGroup;
use App\Ldap\Relations\HasManyThroughChild;
use App\Ldap\Relations\HasOneChild;
use App\Ldap\Relations\HasOneParent;
use App\Ldap\User;

trait HasRelationships{

    public function hasOneChild(array|string $related, string $childDnPrefix): HasOneChild
    {
        return new HasOneChild($this->newQuery(), $this, $related, $childDnPrefix, 'dn');
    }

    public function hasOneParent(array|string $related): HasOneParent
    {
        return new HasOneParent($this->newQuery(), $this, $related, 'dn', 'dn');
    }

    public function hasManyThroughChild(array|string $related, string $childDnPrefix, string $throughAttribute, string $farForeignKey = 'dn'){
        //return $this->hasMany($related, $throughAttribute)->with($this->hasOneChild(AdminGroup::class, $childDnPrefix));
        return new HasManyThroughChild($this->newQuery(), $this, $related, $childDnPrefix, $throughAttribute, $farForeignKey);
    }
}
