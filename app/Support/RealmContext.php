<?php

namespace App\Support;

use App\Ldap\Community;

/**
 * Ambient "which realm is this LDAP auth operation for" context, for the rare
 * call sites that need to scope an LDAP auth-guard query (via
 * App\Ldap\Scopes\ScopedToRealmPeople) without a {realm} route parameter to
 * fall back on - e.g. RegisterUser resolves its community from the email
 * domain, not a URL segment. Bound as a singleton (see AppServiceProvider).
 */
class RealmContext
{
    protected ?Community $realm = null;

    public function set(Community $realm): void
    {
        $this->realm = $realm;
    }

    public function get(): ?Community
    {
        return $this->realm;
    }
}
