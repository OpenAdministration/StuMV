<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRoles extends Component {

    use WithPagination;

    #[Locked]
    public string $uid;
    
    #[Locked]
    public string $ou;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteRoleCn;
    public string $deleteRoleName;

    public bool $showOnlyActive = true;

    public function mount(Community $uid, $ou)
    {
       $this->uid = $uid->getFirstAttribute('ou');
       $this->ou = $ou;
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
        $community = Community::findByUid($this->uid);
        $committee = Committee::findByNameOrFail($this->uid, $this->ou);
        $rolesQuery = $committee->roles();

        if ($this->search) {
            $rolesQuery = $rolesQuery->whereContains('description', $this->search);
        }

        $rolesSlice = $rolesQuery->orderBy('description', 'asc')
            ->list()
            ->get();

        $roleData = [];
        foreach ($rolesSlice as $role) {
            $usernames = $role->dbMemberships()
                ->active(today())
                ->distinct()
                ->pluck('username');

            $members = [];
            foreach ($usernames as $user) {
                $members[] = User::findOrFailByUsername($user);
            }

            $roleData[$role->getDn()] = [
                'hasMembers' => count($members) > 0,
                'members' => $members,
            ];
        }

        return view(
            'livewire.committee.roles', [
                'community' => $community,
                'committee' => $committee,
                'roles' => $rolesSlice,
                'roleData' => $roleData,
            ]
        )->title(__('committees.roles_title', ['name' => $this->ou]));
    }

    public function getMembers(Role $role): array
    {
        $usernames = $role->dbMemberships()
            ->active(today())
            ->distinct()
            ->pluck('username');

        $members = [];
        foreach ($usernames as $user) {
            $members[] = User::findOrFailByUsername($user);
        }
        
        return $members;
    }

    public function getMembersString(Role $role): string
    {
        $usernames = $role->dbMemberships()
            ->active(today())
            ->distinct()
            ->limit(4)
            ->pluck('username');

        $members = [];
        foreach ($usernames as $user) {
            $members[] = User::findOrFailByUsername($user)->getFirstAttribute('cn');
        }
        
        if (count($members) === 4) {
            // replace last one with dots
            array_pop($members);
            $members[] = '…';
        }

        return implode(', ', $members);
    }

    public function getHasMembers(Role $role): string
    {
        $members = $role->dbMemberships()
            ->active(today())
            ->distinct()
            ->limit(1)
            ->pluck('username');
        
        if (count($members) > 0) {
            return true;
        }

        return false;
    }

    #[Computed]
    public function committee() : Committee {
        return Committee::findByName($this->uid, $this->ou);
    }

    #[Computed]
    public function community() : Community {
        return Community::findByUid($this->uid);
    }

    public function deletePrepare($cn): void
    {
        $r = $this->committee()->roles()->where('cn', $cn)->firstOrFail();
        $this->authorize('delete', [$r, $this->committee(), $this->community()]);
        $this->deleteRoleCn = $cn;
        $this->deleteRoleName = $r->getFirstAttribute('description');
        Flux::modal('delete')->show();
    }

    public function deleteCommit()
    {
        $role = $this->committee()?->roles()?->findByOrFail('cn', $this->deleteRoleCn);
        $this->authorize('delete', [$role, $this->committee(), $this->community()]);

        // Delete role memberships
        RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
            ->where('committee_dn', $this->committee()->getDn())
            ->delete();

        // Delete role group relationships
        GroupMembership::where('role_dn', $role->getDn())->delete();

        // Delete role
        $role->delete();

        Flux::toast(variant: 'success', text: __('Role was deleted'));
        return redirect()->route('committees.roles', [
            'uid' => $this->uid,
            'ou' => $this->ou
        ]);
    }

    public function close()
    {
        Flux::modal('delete')->close();
        unset($this->deleteRoleCn, $this->deleteRoleName);
    }
}
