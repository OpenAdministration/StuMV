<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRoles extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'name';
    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    #[Locked]
    public string $uid;
    #[Locked]
    public string $ou;

    public string $deleteRoleCn;
    public string $deleteRoleName;

    /*
     * TODOs:
     *   - Keep member overview in this controller
     *     - Remove dupes from member overview
     */

    public function mount(Community $uid, $ou) {
       $this->uid = $uid->getFirstAttribute('ou');
       $this->ou = $ou;
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
        $community = Community::findByUid($this->uid);
        $committee = Committee::findByNameOrFail($this->uid, $this->ou);
        $rolesSlice = $committee->roles()
            ->search('cn', $this->search)
            ->search('description', $this->search)
            ->slice(1,10);
        return view(
            'livewire.committee.roles', [
                'community' => $community,
                'committee' => $committee,
                'rolesSlice' => $rolesSlice,

            ]
        );
    }

    public function getMembersString(Role $role) : string
    {
        $members = $role->dbMemberships()->distinct()
            ->limit(4)->pluck('username');
        if($members->count() === 4){
            // replace last one with dots
            $members->pop();
            $members->add('...');
        }
        return $members->implode(', ');
    }

    #[Computed]
    public function committee() : Committee {
        return Committee::findByName($this->uid, $this->ou);
    }
    public function deletePrepare($cn): void
    {
        $r = $this->committee()->roles()->where('cn', $cn)->firstOrFail();
        $this->authorize('delete', $r);
        $this->deleteRoleCn = $cn;
        $this->deleteRoleName = $r->getFirstAttribute('description');
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): \Livewire\Features\SupportRedirects\Redirector
    {
        $role = $this->committee()?->roles()?->findByOrFail('cn', $this->deleteRoleCn);
        $this->authorize('delete', $role);
        $role->delete();
        return redirect()->route('committees.roles', ['uid' => $this->uid, 'ou' => $this->ou])
            ->with('status', 'success')
            ->with('message', __('Role was deleted'));
    }

    public function close(): void
    {
        $this->showDeleteModal = false;
        unset($this->deleteRoleCn, $this->deleteRoleName);

    }

}
