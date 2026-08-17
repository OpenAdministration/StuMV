<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Models\ProfilePicture;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use LdapRecord\Models\Model as LdapModel;

class Committees extends Controller
{
    use AuthorizesDirectoryClient;

    public function index(Request $request, Community $realm)
    {
        $this->authorizeClientForCommunity($realm);

        $committees = Committee::fromCommunity($realm->getShortCode())->get()
            ->sortBy(fn (Committee $committee): string => mb_strtolower((string) $committee->getFirstAttribute('description')), SORT_NATURAL);

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
        $roles = $committee->roles()->get()
            ->sortBy(fn (Role $role): string => mb_strtolower((string) $role->getFirstAttribute('description')), SORT_NATURAL);

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
            ->filter(fn ($member) => $member->getFirstAttribute('uid'))
            ->sortBy(fn ($member) => mb_strtolower((string) $member->getFirstAttribute('cn')), SORT_NATURAL)
            ->map(fn ($member) => $member->getFirstAttribute('uid'))
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
     *
     * If "include_roles" is truthy, each member additionally lists which of
     * the requested {ou, cn} roles they actually hold (a person can match
     * more than one), including the role's human-readable name (role_name).
     *
     * Unlike every other Directory API list endpoint (which always sort
     * alphabetically ascending), this one accepts "sort_by"
     * (given_name/family_name, default given_name) and "sort_direction"
     * (asc/desc, default asc) to sort the returned members - the only
     * endpoint where a caller needs that choice.
     */
    public function rolesMembers(Request $request, Community $realm)
    {
        $this->authorizeClientForCommunity($realm);

        $pairs = collect((array) $request->input('roles', []))
            ->filter(fn ($pair): bool => is_array($pair) && filled($pair['ou'] ?? null) && filled($pair['cn'] ?? null));

        abort_if($pairs->isEmpty(), 422, 'At least one {ou, cn} committee/role entry is required.');

        $sortAttributes = ['given_name' => 'givenName', 'family_name' => 'sn'];
        $sortBy = $request->input('sort_by', 'given_name');
        $sortDirection = $request->input('sort_direction', 'asc');

        abort_unless(array_key_exists($sortBy, $sortAttributes), 422, 'sort_by must be one of: '.implode(', ', array_keys($sortAttributes)).'.');
        abort_unless(in_array($sortDirection, ['asc', 'desc'], true), 422, 'sort_direction must be "asc" or "desc".');

        $includeRoles = $request->boolean('include_roles');

        $roleEntries = $pairs
            ->map(function (array $pair) use ($realm): ?array {
                $committee = Committee::findByName($realm->getShortCode(), $pair['ou']);
                $role = $committee?->roles()->where('cn', $pair['cn'])->first();

                return $role ? ['ou' => $pair['ou'], 'role' => $role] : null;
            })
            ->filter();

        $memberEntries = $roleEntries->flatMap(fn (array $entry) => $entry['role']->members()->get()
            ->filter(fn ($member) => $member->getFirstAttribute('uid'))
            ->map(fn ($member) => ['member' => $member, 'ou' => $entry['ou'], 'role' => $entry['role']]));

        $members = $memberEntries
            ->groupBy(fn (array $entry) => $entry['member']->getDn())
            ->map(fn (Collection $group): array => [
                'member' => $group->first()['member'],
                'roles' => $group
                    ->map(fn (array $entry): array => [
                        'ou' => $entry['ou'],
                        'cn' => $entry['role']->getFirstAttribute('cn'),
                        'role_name' => $entry['role']->getFirstAttribute('description'),
                    ])
                    ->unique(fn (array $r): string => $r['ou'].'|'.$r['cn'])
                    ->values(),
            ]);

        $sortAttribute = $sortAttributes[$sortBy];

        $sorted = $members->sortBy(
            fn (array $entry): string => mb_strtolower((string) $entry['member']->getFirstAttribute($sortAttribute)),
            SORT_NATURAL,
            $sortDirection === 'desc',
        );

        return response()->json($sorted
            ->map(fn (array $entry): array => $this->formatMember($entry['member'], $includeRoles ? $entry['roles'] : null))
            ->values());
    }

    private function formatMember(LdapModel $user, ?Collection $roles = null): array
    {
        $picture = ProfilePicture::where('user', $user->getFirstAttribute('uid'))->first();

        $data = [
            'name' => $user->getFirstAttribute('cn'),
            'course' => $user->getFirstAttribute('description'),
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.webp') : null,
        ];

        if ($roles !== null) {
            $data['roles'] = $roles->values()->all();
        }

        return $data;
    }
}
