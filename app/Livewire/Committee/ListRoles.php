<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRoles extends Component
{
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

    public string $deleteConfirmText = '';

    public bool $showOnlyActive = false;

    public bool $ready = false;

    public function mount(Community $realm, $ou)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    public function loadRoles(): void
    {
        $this->ready = true;
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
        // Edit/delete/add-member on any role in this committee, and creating
        // a new one, all resolve to this exact same committee-scoped check
        // regardless of which role - computed once here (an ancestor walk
        // hitting LDAP per level) rather than once per role card, which
        // would otherwise multiply that walk by the number of roles shown.
        $isModerator = auth()->user()->can('moderator', [$committee, $community]);

        if (! $this->ready) {
            return view(
                'livewire.committee.roles', [
                    'community' => $community,
                    'committee' => $committee,
                    'roles' => collect(),
                    'roleData' => [],
                    'isModerator' => $isModerator,
                ]
            )->title(__('committees.roles_title', ['name' => $committee->getFirstAttribute('description')]));
        }

        $rolesQuery = $committee->roles();

        if ($this->search) {
            $rolesQuery = $rolesQuery->whereContains('description', $this->search);
        }

        $rolesSlice = $rolesQuery->list()->get()
            ->sortBy(fn (Role $role): string => mb_strtolower((string) $role->getFirstAttribute('description')), SORT_NATURAL)
            ->values();

        // Collect every role's active-membership usernames first, then
        // resolve all of them from LDAP in a single batched query - doing it
        // per member per role (as before) multiplies an LDAP round trip by
        // the total number of memberships shown across every role.
        $roleUsernames = [];
        foreach ($rolesSlice as $role) {
            $roleUsernames[$role->getDn()] = $role->dbMemberships()
                ->active(today())
                ->distinct()
                ->pluck('username')
                ->all();
        }

        $allUsernames = collect($roleUsernames)->flatten()->unique()->values()->all();
        $userCache = empty($allUsernames)
            ? []
            : User::query()->whereIn('uid', $allUsernames)->get()->keyBy('uid')->all();

        $roleData = [];
        foreach ($rolesSlice as $role) {
            $members = [];
            foreach ($roleUsernames[$role->getDn()] as $username) {
                if (isset($userCache[$username])) {
                    $members[] = $userCache[$username];
                }
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
                'isModerator' => $isModerator,
            ]
        )->title(__('committees.roles_title', ['name' => $this->ou]));
    }

    #[Computed]
    public function committee(): Committee
    {
        return Committee::findByName($this->uid, $this->ou);
    }

    #[Computed]
    public function community(): Community
    {
        return Community::findByUid($this->uid);
    }

    public function deletePrepare($cn): void
    {
        $r = $this->committee()->roles()->where('cn', $cn)->firstOrFail();
        $this->authorize('delete', [$r, $this->committee(), $this->community()]);
        $this->deleteRoleCn = $cn;
        $this->deleteRoleName = $r->getFirstAttribute('description');
        $this->deleteConfirmText = '';
        Flux::modal('delete')->show();
    }

    public function deleteCommit()
    {
        $role = $this->committee()?->roles()?->findByOrFail('cn', $this->deleteRoleCn);
        $this->authorize('delete', [$role, $this->committee(), $this->community()]);

        if ($this->deleteConfirmText !== $this->deleteRoleCn) {
            $this->addError('deleteConfirmText', __('Does not equal :text', ['text' => $this->deleteRoleCn]));

            return;
        }

        // Delete role memberships
        RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
            ->where('committee_dn', $this->committee()->getDn())
            ->delete();

        // Delete role group relationships
        GroupMembership::where('role_dn', $role->getDn())->delete();

        // Delete role
        $role->delete();

        Flux::toast(variant: 'success', text: __('Role was deleted'));

        return to_route('committees.roles', [
            'realm' => $this->uid,
            'ou' => $this->ou,
        ]);
    }

    public function close()
    {
        Flux::modal('delete')->close();
        unset($this->deleteRoleCn, $this->deleteRoleName);
    }
}
