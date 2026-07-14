<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\Group;
use Illuminate\Http\Request;

class Groups extends Controller
{
    use AuthorizesDirectoryClient;

    public function index(Request $request, Community $realm)
    {
        $this->authorizeClientForCommunity($realm);

        $groups = Group::query()->in(Group::dnRoot($realm->getShortCode()))->get();

        return response()->json($groups->map(fn (Group $group): array => [
            'cn' => $group->getFirstAttribute('cn'),
            'description' => $group->getFirstAttribute('description'),
        ])->values());
    }

    public function show(Request $request, Community $realm, string $cn)
    {
        $this->authorizeClientForCommunity($realm);

        $group = Group::query()->in(Group::dnRoot($realm->getShortCode()))->where('cn', $cn)->first() ?? abort(404);

        return response()->json([
            'cn' => $group->getFirstAttribute('cn'),
            'description' => $group->getFirstAttribute('description'),
        ]);
    }

    public function members(Request $request, Community $realm, string $cn)
    {
        $this->authorizeClientForCommunity($realm);

        $group = Group::query()->in(Group::dnRoot($realm->getShortCode()))->where('cn', $cn)->first() ?? abort(404);

        // uniqueMember entries resolve to either Role or User entries -
        // filter down to the actual people (entries carrying a uid).
        $usernames = $group->members()->get()
            ->map(fn ($member) => $member->getFirstAttribute('uid'))
            ->filter()
            ->values();

        return response()->json($usernames);
    }
}
