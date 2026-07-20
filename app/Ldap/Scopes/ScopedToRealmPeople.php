<?php

namespace App\Ldap\Scopes;

use App\Ldap\Community;
use App\Support\RealmContext;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

/**
 * Restricts an LDAP user query to a single realm's People branch. Applied to
 * the LDAP auth guard's provider (config/auth.php), so authentication only
 * ever binds against one specific community - never a global search across
 * every realm at once. Resolves which realm in order of preference:
 *
 *   1. The current route's {realm} parameter - the normal case, covers
 *      {realm}/login and any other realm-scoped auth route.
 *   2. App\Support\RealmContext - an explicit fallback for guest flows that
 *      resolve their community some other way (e.g. RegisterUser derives it
 *      from the registration email's domain, not a URL segment).
 *   3. The already-authenticated user's own realm - covers re-authentication
 *      flows like confirm-password, which aren't realm-scoped URLs at all.
 */
class ScopedToRealmPeople implements Scope
{
    public function apply(Builder $query, Model $model): void
    {
        $realm = request()->route('realm');

        if (! $realm instanceof Community) {
            $realm = resolve(RealmContext::class)->get();
        }

        if (! $realm instanceof Community && ($user = Auth::user()) && $user->realm) {
            $realm = Community::findByUid($user->realm);
        }

        abort_unless($realm instanceof Community, 500,
            'ScopedToRealmPeople could not resolve a realm for this LDAP query.');

        $query->in($realm->peopleDn());
    }
}
