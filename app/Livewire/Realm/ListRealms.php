<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Ldap;
use LdapRecord\Models\OpenLDAP\Group;
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
        $communitySlice = Community::query()
            ->list() // only first level
            ->setDn(Community::$rootDn)
            ->search('ou', $this->search)
            ->search('description', $this->search)
            ->slice(1, 10, $this->sortField, $this->sortDirection);

        $ldapUser = Auth::user()->ldap();
        if($ldapUser->isSuperAdmin()) {
            $canEnter = true;
        } else {
            $memberships = $ldapUser->memberOf;
            $communityMemberships = \Arr::where($memberships, static function (string $value, int $key){
                return preg_match('/^cn=members,ou=[A-Za-z_\-]+,' . Community::rootDn() . '$/', $value);
            });

            $canEnter = \Arr::mapWithKeys($communityMemberships, static function (string $value) {
                $uid = str($value)->remove(',' . Community::rootDn(), false)->remove('cn=members,ou=')->value();
                return [$uid => true];
            });

            if(count($canEnter) === 1){
                $this->redirectRoute('realms.dashboard', ['uid' => \Arr::first(array_keys($canEnter))], navigate: true);
            }
        }


        return view('livewire.realm.list-communities', [
            'realmSlice' => $communitySlice,
            'canEnter' => $canEnter,
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
        $this->redirectRoute('realms.dashboard', ['uid' => $realm_uid]);
    }

}
