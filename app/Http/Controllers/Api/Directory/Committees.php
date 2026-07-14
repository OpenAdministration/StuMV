<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use Illuminate\Http\Request;

class Committees extends Controller
{
    use AuthorizesDirectoryClient;

    public function index(Request $request, Community $uid)
    {
        $this->authorizeClientForCommunity($uid);

        $committees = Committee::fromCommunity($uid->getShortCode())->get();

        return response()->json($committees->map(fn (Committee $committee): array => [
            'ou' => $committee->getFirstAttribute('ou'),
            'description' => $committee->getFirstAttribute('description'),
        ])->values());
    }

    public function show(Request $request, Community $uid, string $ou)
    {
        $this->authorizeClientForCommunity($uid);

        $committee = Committee::findByNameOrFail($uid, $ou);

        return response()->json([
            'ou' => $committee->getFirstAttribute('ou'),
            'description' => $committee->getFirstAttribute('description'),
        ]);
    }

    public function roles(Request $request, Community $uid, string $ou)
    {
        $this->authorizeClientForCommunity($uid);

        $committee = Committee::findByNameOrFail($uid, $ou);
        $roles = $committee->roles()->get();

        return response()->json($roles->map(fn (Role $role): array => [
            'cn' => $role->getFirstAttribute('cn'),
            'description' => $role->getFirstAttribute('description'),
        ])->values());
    }

    public function role(Request $request, Community $uid, string $ou, string $cn)
    {
        $committee = Committee::findByNameOrFail($uid, $ou);
        $role = $committee->roles()->where('cn', $cn)->first() ?? abort(404);

        $this->authorizeClientForCommunity($uid);

        return response()->json([
            'cn' => $role->getFirstAttribute('cn'),
            'description' => $role->getFirstAttribute('description'),
        ]);
    }

    public function roleMembers(Request $request, Community $uid, string $ou, string $cn)
    {
        $committee = Committee::findByNameOrFail($uid, $ou);
        $role = $committee->roles()->where('cn', $cn)->first() ?? abort(404);

        $this->authorizeClientForCommunity($uid);

        // uniqueMember entries resolve to either Role or User entries -
        // filter down to the actual people (entries carrying a uid).
        $usernames = $role->members()->get()
            ->map(fn ($member) => $member->getFirstAttribute('uid'))
            ->filter()
            ->values();

        return response()->json($usernames);
    }
}
