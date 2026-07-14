<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Profile\Memberships;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListMembers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteMemberName = '';

    public string $deleteMemberUsername = '';

    public bool $ready = false;

    public string $community_name;

    public function mount(Community $uid): void
    {
        $this->community_name = $uid->getFirstAttribute('ou');
    }

    public function loadMembers(): void
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
        $community = Community::findOrFailByUid($this->community_name);

        // admin/moderator/remove_member are the same check for every row on
        // this page (they only depend on $community, never on the row's
        // member) - computed once here (admin/moderator each hit LDAP)
        // rather than repeatedly per row and per menu item.
        $isAdmin = auth()->user()->can('admin', $community);
        $isModerator = auth()->user()->can('moderator', $community);
        $canRemoveMember = auth()->user()->can('remove_member', $community);

        if (! $this->ready) {
            return view(
                'livewire.realm.members', [
                    'realm_members' => collect(),
                    'community' => $community,
                    'isAdmin' => $isAdmin,
                    'isModerator' => $isModerator,
                    'canRemoveMember' => $canRemoveMember,
                ]
            )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
        }

        $members = $community->membersGroup()->members()->get();

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $members = $members->filter(fn ($user) => str_contains(mb_strtolower((string) $user->getFirstAttribute('cn')), $search)
                || str_contains(mb_strtolower((string) $user->getFirstAttribute('uid')), $search));
        }

        $sorted = $members
            ->sortBy(fn ($user) => mb_strtolower((string) $user->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $perPage = 10;
        $page = $this->getPage();
        $members = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view(
            'livewire.realm.members', [
                'realm_members' => $members,
                'community' => $community,
                'isAdmin' => $isAdmin,
                'isModerator' => $isModerator,
                'canRemoveMember' => $canRemoveMember,
            ]
        )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
    }

    public function removePrepare($uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $user = User::findOrFailByUsername($uid);
        $this->authorize('remove_member', $community);
        $userBelongsToRealm = $community->membersGroup()->members()->whereEquals('uid', $uid)->get();
        if (! $userBelongsToRealm) {
            // only allow deletes from the same realm
            return;
        }
        $this->deleteMemberName = $user->getFirstAttribute('cn');
        $this->deleteMemberUsername = $uid;
        Flux::modal('remove')->show();
    }

    public function removeCommit(): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $this->authorize('remove_member', $community);
        $user = User::findOrFailByUsername($this->deleteMemberUsername);
        $community->membersGroup()->members()->detach($user);
        Flux::modal('remove')->close();
    }

    public function close(): void
    {
        Flux::modals()->close();
        unset($this->deleteMemberName, $this->deleteMemberUsername);
    }

    public function exportPdf($username)
    {
        $memberships = resolve(Memberships::class)->getMemberships($username, false);
        $user = User::findOrFailByUsername($username);
        $community = Community::findOrFailByUid($this->community_name);
        $pdf = Pdf::loadView('pdfs.memberships', [
            'fullName' => $user->cn[0],
            'community' => $community->description[0],
            'memberships' => $memberships,
        ]);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->stream();
        }, 'memberships-'.$username.'.pdf');
    }
}
