<?php

namespace App\Livewire\Group;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Models\GroupMembership;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRolesInGroup extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'committee';

    #[Url]
    public string $sortDirection = 'asc';

    public string $group_dn;

    public string $group_cn;

    public string $realm_uid;

    public ?int $deleteGroupRoleId = null;

    public array $deleteRoleName = [];

    public function mount(Community $realm, $cn)
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
        $this->group_cn = $cn;
        $this->group_dn = Group::dnFrom($this->realm_uid, $cn);
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $groupRoles = GroupMembership::query()
            ->where('group_dn', $this->group_dn)
            ->get();

        // A single subtree search under the realm's committee tree is one
        // LDAP round-trip regardless of how many distinct roles/committees
        // are referenced below - findMany() by contrast issues one
        // read-by-DN query per entry, so it wouldn't actually save anything
        // here once there's more than a handful of rows.
        $rolesByDn = $groupRoles->isEmpty()
            ? collect()
            : Role::query()->in(Committee::dnRoot($this->realm_uid))->get()
                ->keyBy(fn (Role $role) => $role->getDn())
                ->only($groupRoles->pluck('role_dn')->unique()->all());

        $committeesByDn = $rolesByDn->isEmpty()
            ? collect()
            : Committee::fromCommunity($this->realm_uid)->get()
                ->keyBy(fn (Committee $committee) => $committee->getDn());

        $rows = $groupRoles
            ->map(function (GroupMembership $groupRole) use ($rolesByDn, $committeesByDn): ?array {
                $role = $rolesByDn->get($groupRole->role_dn);

                if (! $role) {
                    return null;
                }

                return [
                    'groupRole' => $groupRole,
                    'role' => $role,
                    'committee' => $committeesByDn->get($role->getParentDn()),
                ];
            })
            ->filter()
            ->values();

        if ($this->search) {
            $search = mb_strtolower(trim($this->search));
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $role = $row['role'];
                $committee = $row['committee'];

                return str_contains(mb_strtolower((string) $role->getFirstAttribute('description')), $search)
                    || str_contains(mb_strtolower((string) $committee?->getFirstAttribute('description')), $search);
            })->values();
        }

        $rows = $rows->sortBy(function (array $row): string {
            $sortValue = $this->sortField === 'role'
                ? $row['role']->getFirstAttribute('description')
                : $row['committee']?->getFirstAttribute('description');

            return mb_strtolower((string) $sortValue);
        }, SORT_NATURAL, $this->sortDirection === 'desc')->values();

        $perPage = 10;
        $page = $this->getPage();
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
        );

        return view(
            'livewire.group.roles', [
                'rows' => $paginated,
            ]
        )->title(__('groups.roles_list_title', ['name' => $this->group_cn]));
    }

    public function deletePrepare(int $groupRoleId): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        $groupRole = GroupMembership::findOrFail($groupRoleId);
        $role = Role::findOrFail($groupRole->role_dn);

        $this->deleteGroupRoleId = $groupRoleId;
        $this->deleteRoleName = ['name' => $role->getFirstAttribute('cn')];

        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        GroupMembership::whereKey($this->deleteGroupRoleId)->delete();

        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteGroupRoleId, $this->deleteRoleName);
        Flux::modal('delete')->close();
    }
}
