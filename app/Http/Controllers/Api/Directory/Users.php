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

    public function show(Request $request, Community $realm, string $uid)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $uid);

        $picture = ProfilePicture::where('user', $uid)->first();

        return response()->json([
            'uid' => $user->getFirstAttribute('uid'),
            'name' => $user->getFirstAttribute('cn'),
            'given_name' => $user->getFirstAttribute('givenName'),
            'family_name' => $user->getFirstAttribute('sn'),
            'course' => $user->getFirstAttribute('description'),
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.webp') : null,
        ]);
    }

    public function roles(Request $request, Community $realm, string $uid)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $uid);

        $roles = $this->userRoles($realm, $user)
            ->map(function (Role $role): array {
                $committee = $role->committee();

                return [
                    'ou' => $committee?->getFirstAttribute('ou'),
                    'cn' => $role->getFirstAttribute('cn'),
                ];
            })
            ->sortBy(fn (array $role): string => mb_strtolower($role['ou'].'|'.$role['cn']), SORT_NATURAL);

        return response()->json($roles->values());
    }

    public function committees(Request $request, Community $realm, string $uid)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $uid);

        $committees = $this->userRoles($realm, $user)
            ->map(fn (Role $role): ?Committee => $role->committee())
            ->filter()
            ->unique(fn (Committee $committee): string => $committee->getDn())
            ->map(fn (Committee $committee): string => $committee->getFirstAttribute('ou'))
            ->sortBy(fn (string $ou): string => mb_strtolower($ou), SORT_NATURAL);

        return response()->json($committees->values());
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

    public function groups(Request $request, Community $realm, string $uid)
    {
        $this->authorizeClientForCommunity($realm);

        $user = $this->findMemberOrFail($realm, $uid);

        $groups = Group::query()->in(Group::dnRoot($realm->getShortCode()))
            ->where('uniqueMember', '=', $user->getDn())
            ->get()
            ->map(fn (Group $group): string => $group->getFirstAttribute('cn'))
            ->sortBy(fn (string $cn): string => mb_strtolower($cn), SORT_NATURAL);

        return response()->json($groups->values());
    }

    /**
     * Keep the lookup realm-bound: search directly under this community's
     * own People branch, rather than a global uid search - simpler, and
     * correct now that the same uid can independently exist in other realms.
     */
    private function findMemberOrFail(Community $realm, string $uid): LdapUser
    {
        return LdapUser::query()->in($realm->peopleDn())->where('uid', '=', $uid)->first() ?? abort(404);
    }
}
