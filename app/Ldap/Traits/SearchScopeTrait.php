<?php

namespace App\Ldap\Traits;
use LdapRecord\Query\Builder;

trait SearchScopeTrait{

    /**
     * @param Builder $query
     * @param string $attributeName
     * @param string $searchString
     * @param string $operator available operators are:
     * '=' "Equals" clause
     * '!' "Not equals" clause
     * '!=' Not equals alias
     * '*'  "Has" clause
     * '!*' "Does not have" clause
     * '>=' "Greater than or equal to" clause
     * '<=' "Less than or equal to" clause
     * '~=' "Approximately equal to" clause
     * 'starts_with'
     * 'not_starts_with'
     * 'ends_with'
     * 'not_ends_with'
     * 'contains'
     * 'not_contains'
     * @return void
     */
    public function scopeSearch(Builder $query, string $attributeName, ?string $searchString, string $operator = 'contains'): void
    {
        if(!empty($searchString)){
            $query->OrWhere($attributeName, $operator, $searchString);
        }
    }
}
