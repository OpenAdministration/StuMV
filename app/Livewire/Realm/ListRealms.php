<?php

namespace App\Livewire\Realm;

use App\Ldap\Committee;
use App\Ldap\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRealms extends Component
{
    use WithPagination;
    use AuthorizesRequests;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'uid';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;
    public string $deleteRealmName = '';


    public function mount(){
        session()->forget('realm_uid');
        session()->save();
    }

    public function sortBy($field): void
    {
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

    public function render(Request $request)
    {
        session()->forget('realm_uid');
        session()->save();

        $communitySlice = Community::query()
            ->list() // only first level
            ->setDn(Community::$rootDn)
            ->search('ou', $this->search)
            ->search('description', $this->search)
            ->slice(1, 10, $this->sortField, $this->sortDirection);

        return view('livewire.realm.list-communities', [
            'realmSlice' => $communitySlice,
        ]);
    }

    public function deletePrepare($uid): void
    {
        $c = Community::findOrFailByUid($uid);
        $this->authorize('delete', $c);
        $this->deleteRealmName = $uid;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->deleteRealmName);
        $this->authorize('delete', $community);
        $community->delete(recursive: true);
        // reset everything to prevent a 404 modal
        unset($this->deleteRealmName);
        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showDeleteModal = false;
    }

    /**
     * @param $realm_uid string the selected realm_uid
     * @return void
     */
    public function enter(string $realm_uid){
        $c = Community::findOrFailByUid($realm_uid);
        $this->authorize('enter', $c);
        session(['realm_uid' => $realm_uid]);
        $this->redirectRoute('realms.dashboard', ['uid' => $realm_uid]);
    }

}
