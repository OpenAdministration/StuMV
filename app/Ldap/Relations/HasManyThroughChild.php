<?php

namespace App\Ldap\Relations;

use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\OneToMany;
use LdapRecord\Models\Relations\Relation;
use LdapRecord\Query\Model\Builder;

class HasManyThroughChild extends OneToMany {

    protected string $childDnPrefix;
    protected Model $child;

    public function getChildModel() : Model{
        if(!isset($this->child)){

        }
        return $this->child;
    }

    /**
     * @param Builder $query
     * @param Model $parent the parent Model the child is searched for
     * @param array|string $related the Classname where results should be in
     * @param string $childDnPrefix the DN Prefix for the parent DN
     * @param string $relationKey the key attribute at the child model
     * @param string $foreignKey the foreign key attribute at the related class
     */
    public function __construct(Builder $query, Model $parent, array|string $related, string $childDnPrefix, string $relationKey, string $foreignKey){
        $this->childDnPrefix = $childDnPrefix;
        parent::__construct($query, $parent, $related, $relationKey, $foreignKey, null);

        $dn = $this->childDnPrefix . ',' . $this->parent->getDn();
        $childModel = $this->getQuery()->setDn($dn)->first();
        $this->using($childModel, $relationKey);
    }


    public function getRelationResults(): Collection
    {
        $results = $this->parent->newCollection();

        foreach ((array) $this->using->getAttribute($this->relationKey) as $value) {
            if ($value && $foreign = $this->getForeignModelByValue($value)) {
                $results->push($foreign);
            }
        }

        return $this->transformResults($results);
    }


}
