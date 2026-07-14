<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Models\GroupMembership;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRolesInGroup extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'name';

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

        $rolesByDn = $groupRoles->isEmpty()
            ? collect()
            : Role::query()->findMany($groupRoles->pluck('role_dn')->unique()->all())
                ->keyBy(fn (Role $role) => $role->getDn());

        $rows = $groupRoles
            ->map(fn (GroupMembership $groupRole): array => [
                'groupRole' => $groupRole,
                'role' => $rolesByDn->get($groupRole->role_dn),
            ])
            ->filter(fn (array $row): bool => $row['role'] !== null)
            ->values();

        return view(
            'livewire.group.roles', [
                'rows' => $rows,
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
