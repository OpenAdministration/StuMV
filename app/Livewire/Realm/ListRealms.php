<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public string $sortField = 'description';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteRealmName = '';

    public string $deleteConfirmText = '';

    public bool $showOnlyMine = false;

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

    public function updatedShowOnlyMine(): void
    {
        $this->resetPage();
    }

    /**
     * Redirecting straight to a single-community member's dashboard has to
     * happen here, not in render(): a redirect called from inside render()
     * only sets the "redirect" effect for the response - it doesn't stop the
     * rest of render() (and the full picker view it returns) from still
     * executing. A redirect from mount() runs before Livewire decides
     * whether to call render() at all, so it genuinely skips building the
     * picker view.
     */
    public function mount(): void
    {
        $ldapUser = Auth::user()->ldap();

        if ($ldapUser->isSuperAdmin()) {
            return;
        }

        $canEnter = $this->communityMemberships($ldapUser);

        if (count($canEnter) === 1) {
            $this->redirectRoute('realms.dashboard', ['uid' => \Arr::first(array_keys($canEnter))], navigate: true);
        }
    }

    public function render(Request $request)
    {
        $communityQuery = Community::query()
            ->setDn(Community::$rootDn)->search()
            ->list();

        if ($this->search) {
            $search = trim($this->search);
            $communityQuery->whereContains('ou', $search)
                ->orWhereContains('description', $search);
        }

        $sorted = $communityQuery->get()
            ->sortBy(fn (Community $community): string => mb_strtolower((string) $community->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $ldapUser = Auth::user()->ldap();
        // Actual LDAP membership, independent of superadmin status - used
        // both for the "only mine" filter below and (for non-superadmins)
        // to gate the Enter button per row.
        $myMemberships = $this->communityMemberships($ldapUser);

        if ($this->showOnlyMine) {
            $sorted = $sorted
                ->filter(fn (Community $community): bool => array_key_exists($community->getShortCode(), $myMemberships))
                ->values();
        }

        // LdapRecord collections aren't Eloquent builders, so pagination is
        // done manually: sort the full result, then slice out the page.
        $perPage = 10;
        $page = $this->getPage();
        $communitySlice = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        $canEnter = $ldapUser->isSuperAdmin() ? true : $myMemberships;

        return view('livewire.realm.list-communities', [
            'realms' => $communitySlice,
            'canEnter' => $canEnter,
        ])->title(__('realms.list_title'));
    }

    /**
     * @return array<string, true> community short codes this user is a member of
     */
    private function communityMemberships($ldapUser): array
    {
        $memberships = $ldapUser->memberOf;
        $communityMemberships = \Arr::where($memberships, static fn (string $value, int $key) => preg_match('/^cn=members,ou=[0-9A-Za-z_\-]+,'.Community::rootDn().'$/', $value));

        return \Arr::mapWithKeys($communityMemberships, static function (string $value) {
            $uid = str($value)->remove(','.Community::rootDn(), false)->remove('cn=members,ou=')->value();

            return [$uid => true];
        });
    }

    public function deletePrepare($uid): void
    {
        $c = Community::findOrFailByUid($uid);
        $this->authorize('delete', $c);
        $this->deleteRealmName = $uid;
        $this->deleteConfirmText = '';
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->deleteRealmName);
        $this->authorize('delete', $community);

        if ($this->deleteConfirmText !== $this->deleteRealmName) {
            $this->addError('deleteConfirmText', __('Does not equal :text', ['text' => $this->deleteRealmName]));

            return;
        }

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
