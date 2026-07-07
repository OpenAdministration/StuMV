<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListMembers extends Component {

    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $sortField = 'full_name';
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

        if (! $this->ready) {
            return view(
                'livewire.realm.members', [
                    'realm_members' => collect(),
                    'community' => $community,
                    'ldap_users' => collect(),
                ]
            )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
        }

        $membersQuery = \App\Models\User::where('realm', $this->community_name)
            ->orderBy($this->sortField, $this->sortDirection);

        if ($this->search != '') {
            $membersQuery->where('full_name', 'like', '%' . $this->search . '%');
            $membersQuery->orWhere('username', 'like', '%' . $this->search . '%');
        }

        $members = $membersQuery->paginate(10);
        $ldapUsers = collect();
        $usernames = $members->pluck('username')->filter()->values()->all();

        if (! empty($usernames)) {
            $ldapUsers = User::query()->whereIn('uid', $usernames)->get()->keyBy('uid');
        }

        return view(
            'livewire.realm.members', [
                'realm_members' => $members,
                'community' => $community,
                'ldap_users' => $ldapUsers,
            ]
        )->title(__('realms.members_title', ['name' => $community->getLongName(), 'uid' => $community->getShortCode()]));
    }

    public function removePrepare($uid): void
    {
        $community = Community::findOrFailByUid($this->community_name);
        $user = User::findOrFailByUsername($uid);
        $this->authorize('remove_member', $community);
        $userBelongsToRealm = $community->membersGroup()->members()->whereEquals('uid', $uid)->get();
        if(!$userBelongsToRealm) {
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
        $memberships = app('App\Livewire\Profile\Memberships')->getMemberships($username, false);
        $user = User::findOrFailByUsername($username);
        $community = Community::findOrFailByUid($this->community_name);
        $pdf = Pdf::loadView('pdfs.memberships', [
            'fullName' => $user->cn[0],
            'community' => $community->description[0],
            'memberships' => $memberships,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'memberships-' . $username . '.pdf');;
    }
}
