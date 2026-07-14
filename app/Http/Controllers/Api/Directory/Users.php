<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\User as LdapUser;
use App\Models\ProfilePicture;
use Illuminate\Http\Request;

class Users extends Controller
{
    use AuthorizesDirectoryClient;

    public function show(Request $request, Community $realm, string $username)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $username);

        $picture = ProfilePicture::where('user', $username)->first();

        return response()->json([
            'uid' => $user->getFirstAttribute('uid'),
            'name' => $user->getFirstAttribute('cn'),
            'given_name' => $user->getFirstAttribute('givenName'),
            'family_name' => $user->getFirstAttribute('sn'),
            'course' => $user->getFirstAttribute('description'),
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.jpg') : null,
        ]);
    }

    public function roles(Request $request, Community $realm, string $username)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $username);

        $roles = $this->userRoles($realm, $user);

        return response()->json($roles->map(function (Role $role): array {
            $committee = $role->committee();

            return [
                'ou' => $committee?->getFirstAttribute('ou'),
                'cn' => $role->getFirstAttribute('cn'),
            ];
        })->values());
    }

    public function committees(Request $request, Community $realm, string $username)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $username);

        $committees = $this->userRoles($realm, $user)
            ->map(fn (Role $role): ?Committee => $role->committee())
            ->filter()
            ->unique(fn (Committee $committee): string => $committee->getDn());

        return response()->json($committees->map(fn (Committee $committee): string => $committee->getFirstAttribute('ou'))->values());
    }

    /**
     * Roles anywhere in the community's committee tree (arbitrarily nested)
     * that currently have this user as an actual LDAP member - the same
     * source of truth Committees::roleMembers() reads from. uniqueMember is
     * DN-syntax, which OpenLDAP only indexes/matches for equality, not
     * substrings - whereContains() silently returns nothing.
     */
    private function userRoles(Community $realm, LdapUser $user)
    {
        return Role::query()->in(Committee::dnRoot($realm->getShortCode()))
            ->where('uniqueMember', '=', $user->getDn())
            ->get();
    }

    public function groups(Request $request, Community $realm, string $username)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $username);

        $groups = Group::query()->in(Group::dnRoot($realm->getShortCode()))
            ->where('uniqueMember', '=', $user->getDn())
            ->get();

        return response()->json($groups->map(fn (Group $group): string => $group->getFirstAttribute('cn'))->values());
    }

    /**
     * Keep the lookup realm-bound: only expose users who are actually a
     * member of this community, not any LDAP account anywhere.
     */
    private function findMemberOrFail(Community $realm, string $username): LdapUser
    {
        $user = LdapUser::findByUsername($username) ?? abort(404);

        abort_unless($realm->membersGroup()->members()->exists($user), 404);

        return $user;
    }
}
