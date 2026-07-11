<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListRealms extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'uid';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteRealmName = '';

    public function sortBy($field): void
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

    public function render(Request $request)
    {
        $communitySlice = Community::query()
            ->setDn(Community::$rootDn)->search()->search()
            ->list()
            ->get();

        $ldapUser = Auth::user()->ldap();
        if ($ldapUser->isSuperAdmin()) {
            $canEnter = true;
        } else {
            $memberships = $ldapUser->memberOf;
            $communityMemberships = \Arr::where($memberships, static fn (string $value, int $key) => preg_match('/^cn=members,ou=[0-9A-Za-z_\-]+,'.Community::rootDn().'$/', $value));

            $canEnter = \Arr::mapWithKeys($communityMemberships, static function (string $value) {
                $uid = str($value)->remove(','.Community::rootDn(), false)->remove('cn=members,ou=')->value();

                return [$uid => true];
            });

            if (count($canEnter) === 1) {
                $this->redirectRoute('realms.dashboard', ['uid' => \Arr::first(array_keys($canEnter))], navigate: true);
            }
        }

        return view('livewire.realm.list-communities', [
            'realms' => $communitySlice,
            'canEnter' => $canEnter,
        ])->title(__('realms.list_title'));
    }

    public function deletePrepare($uid): void
    {
        $c = Community::findOrFailByUid($uid);
        $this->authorize('delete', $c);
        $this->deleteRealmName = $uid;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->deleteRealmName);
        $this->authorize('delete', $community);
        $community->delete(recursive: true);
        // reset everything to prevent a 404 modal
        unset($this->deleteRealmName);
        Flux::modal('delete')->close();
    }

    public function close(): void
    {
        Flux::modals()->close();
    }

    /**
     * @param  $realm_uid  string the selected realm_uid
     * @return void
     */
    public function enter(string $realm_uid)
    {
        $c = Community::findOrFailByUid($realm_uid);
        $this->authorize('enter', $c);
        $this->redirectRoute('realms.dashboard', ['uid' => $realm_uid]);
    }
}
