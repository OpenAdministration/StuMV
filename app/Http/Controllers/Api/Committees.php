<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Role;
use App\Ldap\User;
use Illuminate\Http\Request;

class Committees extends Controller
{
    public function all(Request $request)
    {
        $committees = $this->prepareData(fn (string $dn) => str_contains($dn, 'ou=Committees'));

        return response()->json($committees);
    }

    public function fromCommunity(Request $request, string $community_uid)
    {
        $committees = $this->prepareData(fn (string $dn) => str_contains($dn, "ou=Committees,ou=$community_uid"));

        return response()->json($committees);
    }

    private function prepareData(?callable $filter = null): array
    {
        /** @var User $ldapUser */
        $ldapUser = \Auth::user()->ldap();
        $userDn = $ldapUser->getDn();

        $roles = Role::query()->where('uniqueMember', $userDn)->get();

        $committeeDns = $roles->map(fn ($item) => $item->getParentDn())->filter($filter)->toArray();
        // Issue: you cannot query "DN in (x,y,z)" - therefore multiple single finds collected
        $committees = collect();
        foreach ($committeeDns as $committeeDn) {
            $committees->add(Committee::find($committeeDn));
        }

        // returns array of committees like "stura" => "Studierendenrat"
        // FIXME: has issues with all() -> not distinguishable in multi realm setup
        return $committees
            ->keyBy(
                // change key
                fn ($item) => $item->getFirstAttribute('ou'))->map(
                    // change value
                    fn ($item) => $item->getFirstAttribute('description'))->toArray();
    }
}
