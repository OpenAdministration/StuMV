<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Ldap\User;
use App\Models\RoleMembership;
use App\Models\UniLdap;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UsersNotInUniLdap extends Component
{
    #[Locked]
    public string $uid;

    public bool $unildapDataExists = false;

    public array $results = [];

    public bool $comparisonCompleted = false;

    public string $userToDelete = "";

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $unildap = UniLdap::where('realm', $this->uid)->first();
        if ($unildap !== null) {
            $this->unildapDataExists = true;
        }
    }

    public function render()
    {
        return view('livewire.tools.users-not-in-uni-ldap');
    }

    public function searchForUsersNotInUniLdap()
    {
        $this->comparisonCompleted = false;
        $this->results = [];

        $community = Community::findOrFailByUid($this->uid);
        $members = $community->membersGroup()->members()->get();

        $domains = [];
        $domainEntries = Domain::fromCommunity($this->uid)->get();
        foreach ($domainEntries as $item) {
            $domains[] = $item->dc[0];
        }
        
        $membersUniQuery = App\Ldap\Uni\User::query();
        foreach ($members as $member) {
            $memberEmailParts = explode('@', $member->email);
            if (in_array($memberEmailParts[1], $domains)) {
                $membersUniQuery->orWhere('mail', '=', $member->email);
            }
        }
        $membersUni = $membersUniQuery->get();

        foreach ($members as $member) {
            if (!$membersUni->contains(fn ($uniMember) => $uniMember->getFirstAttribute('mail') === $member->email)) {
                $this->results[] = $member;
            }
        }

        $this->comparisonCompleted = true;
    }

    public function confirmDeleteUser(string $username)
    {
        $this->userToDelete = $username;
        Flux::modal('confirm-delete-user')->show();
    }

    public function deleteUser()
    {
        $community = Community::findByOrFail('ou', $this->uid);
        $this->authorize('remove_member', $community);
        
        // LDAP
        $user = User::findOrFailByUsername($this->userToDelete);
        $community->membersGroup()->members()->detach($user);
        $user->delete();

        // Database
        RoleMembership::where('username', $this->userToDelete)->delete();
        User::where('username', $this->userToDelete)->delete();

        Flux::toast(variant: 'success', text: __('tools.userDeletedSuccessfully'));
        Flux::modal('confirm-delete-user')->close();
    }
}
