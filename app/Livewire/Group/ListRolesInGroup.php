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

class ListRolesInGroup extends Component {

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

    public string $deleteRoleDN = "";
    public array $deleteRoleName = [];

    public function mount(Community $uid, $cn) {
        $this->realm_uid = $uid->getFirstAttribute('ou');
        $this->group_cn = $cn;
        $this->group_dn = Group::dnFrom($this->realm_uid, $cn);
    }

    public function sortBy($field){
        if($this->sortField === $field){
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }else{
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render() {
        $roleDns = GroupMembership::query()
            ->where('group_dn', $this->group_dn)
            ->distinct()
            ->pluck('role_dn')
            ->all();

        $roles = empty($roleDns)
            ? collect()
            : Role::query()->findMany($roleDns);

        return view(
            'livewire.group.roles', [
                'roles' => $roles,
            ]
        )->title(__('groups.roles_list_title', ['name' => $this->group_cn]));
    }

    public function deletePrepare(string $role_dn): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        $group = Group::findOrFail($this->group_dn);
        $role = Role::findOrFail($role_dn);
        $committee = $role->committee();

        $this->deleteRoleDN = $role_dn;
        $this->deleteRoleName = [ $role->getFirstAttribute('cn') ];

        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $this->authorize('delete', [Group::class, $community]);

        GroupMembership::where('group_dn', $this->group_dn)
            ->where('role_dn', $this->deleteRoleDN)
            ->delete();

        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteRoleDN, $this->deleteRoleName);
        Flux::modal('delete')->close();
    }
}
