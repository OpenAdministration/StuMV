<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\User;
use Illuminate\Http\Request;

class Groups extends Controller
{
    public function all(Request $request)
    {
        // no filter applied
        $groups = $this->prepareData(fn (string $dn) => true);

        return response()->json($groups);
    }

    public function fromCommunity(Request $request, string $community_uid)
    {
        // only one specific community as filter
        $groups = $this->prepareData(fn (string $dn) => str_contains($dn, "ou=Committees,ou=$community_uid"));

        return response()->json($groups);
    }

    /**
     * Returns all Group memberships (not roles) as array, can be filtered
     *
     * @param  callable  $filter  the filter which should be applied to the collected result
     * @return array the fetched groups
     */
    private function prepareData(callable $filter): array
    {
        /** @var User $ldapUser */
        $ldapUser = \Auth::user()->ldap();
        $userDn = $ldapUser->getDn();

        $roles = Role::query()->where('uniqueMember', $userDn)->get();

        $groupsQuery = Group::query()->orWhere('uniqueMember', '=', $userDn);
        foreach ($roles as $role) {
            $groupsQuery->orWhere('uniqueMember', '=', $role->getDn());
        }
        $groups = $groupsQuery->get();

        // returns the (filtered) group DNs as array
        return $groups->map(fn ($item) => $item->getDn())->reject(
            // throw out the committee roles. only memberships and permissions inside
            fn (string $dn) => str_contains($dn, 'ou=Committees'))->filter($filter)
            ->toArray();
    }
}
