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

    public function show(Request $request, Community $uid, string $username)
    {
        $this->authorizeClientForCommunity($uid);

        $user = $this->findMemberOrFail($uid, $username);

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

    public function roles(Request $request, Community $uid, string $username)
    {
        $this->authorizeClientForCommunity($uid);

        $user = $this->findMemberOrFail($uid, $username);

        $roles = $this->userRoles($uid, $user);

        return response()->json($roles->map(function (Role $role): array {
            $committee = $role->committee();

            return [
                'committee' => $committee?->getFirstAttribute('ou'),
                'role' => $role->getFirstAttribute('cn'),
            ];
        })->values());
    }

    public function committees(Request $request, Community $uid, string $username)
    {
        $this->authorizeClientForCommunity($uid);

        $user = $this->findMemberOrFail($uid, $username);

        $committees = $this->userRoles($uid, $user)
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
    private function userRoles(Community $uid, LdapUser $user)
    {
        return Role::query()->in(Committee::dnRoot($uid->getShortCode()))
            ->where('uniqueMember', '=', $user->getDn())
            ->get();
    }

    public function groups(Request $request, Community $uid, string $username)
    {
        $this->authorizeClientForCommunity($uid);

        $user = $this->findMemberOrFail($uid, $username);

        $groups = Group::query()->in(Group::dnRoot($uid->getShortCode()))
            ->where('uniqueMember', '=', $user->getDn())
            ->get();

        return response()->json($groups->map(fn (Group $group): array => [
            'cn' => $group->getFirstAttribute('cn'),
            'description' => $group->getFirstAttribute('description'),
        ])->values());
    }

    /**
     * Keep the lookup realm-bound: only expose users who are actually a
     * member of this community, not any LDAP account anywhere.
     */
    private function findMemberOrFail(Community $uid, string $username): LdapUser
    {
        $user = LdapUser::findByUsername($username) ?? abort(404);

        abort_unless($uid->membersGroup()->members()->exists($user), 404);

        return $user;
    }
}
