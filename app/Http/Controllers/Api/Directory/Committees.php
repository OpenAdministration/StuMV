<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\ProfilePicture;
use Illuminate\Http\Request;
use LdapRecord\Models\Model as LdapModel;

class Committees extends Controller
{
    use AuthorizesDirectoryClient;

    public function index(Request $request, Community $realm)
    {
        $this->authorizeClientForCommunity($realm);

        $committees = Committee::fromCommunity($realm->getShortCode())->get();

        return response()->json($committees->map(fn (Committee $committee): array => [
            'ou' => $committee->getFirstAttribute('ou'),
            'description' => $committee->getFirstAttribute('description'),
        ])->values());
    }

    public function show(Request $request, Community $realm, string $ou)
    {
        $this->authorizeClientForCommunity($realm);

        $committee = Committee::findByNameOrFail($realm, $ou);

        return response()->json([
            'ou' => $committee->getFirstAttribute('ou'),
            'description' => $committee->getFirstAttribute('description'),
        ]);
    }

    public function roles(Request $request, Community $realm, string $ou)
    {
        $this->authorizeClientForCommunity($realm);

        $committee = Committee::findByNameOrFail($realm, $ou);
        $roles = $committee->roles()->get();

        return response()->json($roles->map(fn (Role $role): array => [
            'cn' => $role->getFirstAttribute('cn'),
            'description' => $role->getFirstAttribute('description'),
        ])->values());
    }

    public function role(Request $request, Community $realm, string $ou, string $cn)
    {
        $committee = Committee::findByNameOrFail($realm, $ou);
        $role = $committee->roles()->where('cn', $cn)->first() ?? abort(404);

        $this->authorizeClientForCommunity($realm);

        return response()->json([
            'cn' => $role->getFirstAttribute('cn'),
            'description' => $role->getFirstAttribute('description'),
        ]);
    }

    public function roleMembers(Request $request, Community $realm, string $ou, string $cn)
    {
        $committee = Committee::findByNameOrFail($realm, $ou);
        $role = $committee->roles()->where('cn', $cn)->first() ?? abort(404);

        $this->authorizeClientForCommunity($realm);

        // uniqueMember entries resolve to either Role or User entries -
        // filter down to the actual people (entries carrying a uid).
        $usernames = $role->members()->get()
            ->map(fn ($member) => $member->getFirstAttribute('uid'))
            ->filter()
            ->values();

        return response()->json($usernames);
    }

    /**
     * Members holding the role given by each {ou, cn} entry in "roles",
     * deduplicated by person. Reads the same LDAP uniqueMember source of
     * truth as roleMembers() above (not RoleMembership - see
     * Users::userRoles()'s doc comment for why), and returns the same
     * name/course/picture fields as Users::show() for each matched person.
     * An entry naming an unknown committee or role is silently skipped
     * rather than 404ing, since this is a filter over many possible values
     * rather than a lookup of one specific resource.
     */
    public function rolesMembers(Request $request, Community $realm)
    {
        $this->authorizeClientForCommunity($realm);

        $pairs = collect((array) $request->input('roles', []))
            ->filter(fn ($pair): bool => is_array($pair) && filled($pair['ou'] ?? null) && filled($pair['cn'] ?? null));

        abort_if($pairs->isEmpty(), 422, 'At least one {ou, cn} committee/role entry is required.');

        $members = $pairs
            ->map(function (array $pair) use ($realm): ?Role {
                $committee = Committee::findByName($realm->getShortCode(), $pair['ou']);

                return $committee?->roles()->where('cn', $pair['cn'])->first();
            })
            ->filter()
            ->flatMap(fn (Role $role) => $role->members()->get())
            ->filter(fn ($member) => $member->getFirstAttribute('uid'))
            ->unique(fn ($member) => $member->getDn());

        return response()->json($members->map(fn (LdapModel $member): array => $this->formatMember($member))->values());
    }

    private function formatMember(LdapModel $user): array
    {
        $picture = ProfilePicture::where('user', $user->getFirstAttribute('uid'))->first();

        return [
            'name' => $user->getFirstAttribute('cn'),
            'course' => $user->getFirstAttribute('description'),
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.webp') : null,
        ];
    }
}
