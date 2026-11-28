<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Committees extends Controller
{
    public function all(Request $request): JsonResponse
    {
        $committees = $this->prepareData(fn (string $dn): bool => str_contains($dn, 'ou=Committees'));

        return response()->json($committees);
    }

    public function fromCommunity(Request $request, string $community_uid): JsonResponse
    {
        $committees = $this->prepareData(fn (string $dn): bool => str_contains($dn, "ou=Committees,ou=$community_uid"));

        return response()->json($committees);
    }

    /** @return array<string, mixed> */
    private function prepareData(?callable $filter = null): array
    {
        $ldapUser = User::current()->ldap();
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
                fn ($item): string => $item->getFirstAttribute('ou'))->map(
                    // change value
                    fn ($item) => $item->getFirstAttribute('description'))->toArray();
    }
}
