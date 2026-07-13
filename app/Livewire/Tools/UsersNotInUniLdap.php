<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Ldap\Uni\User as UniLdapUser;
use App\Ldap\User;
use App\Models\RoleMembership;
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

    public string $userToDelete = '';

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->unildapDataExists = filled(config('ldap.connections.uni.base_dn'));
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

        // Only members whose email belongs to one of this realm's registered
        // domains are relevant to look up in the uni LDAP at all.
        $candidates = $members->filter(function ($member) use ($domains): bool {
            $emailParts = explode('@', (string) $member->getFirstAttribute('mail'));

            return isset($emailParts[1]) && in_array($emailParts[1], $domains, true);
        });

        $mails = $candidates
            ->map(fn ($member) => $member->getFirstAttribute('mail'))
            ->filter()
            ->unique()
            ->values();

        // Batched lookups instead of one uni LDAP query per candidate, but
        // the uni LDAP server caps each search request's results, so the
        // batches themselves must stay at or under that configured size.
        $mailsFoundInUniLdap = $mails
            ->chunk(config('ldap.uni_batch_size', 10))
            ->flatMap(fn ($chunk) => UniLdapUser::whereIn('mail', $chunk->all())->get())
            ->map(fn (UniLdapUser $user) => $user->getFirstAttribute('mail'))
            ->all();

        foreach ($candidates as $member) {
            if (! in_array($member->getFirstAttribute('mail'), $mailsFoundInUniLdap, true)) {
                // Livewire can't serialize a raw LdapRecord model as a
                // public property value, so keep only the plain fields
                // the view actually needs.
                $this->results[] = [
                    'uid' => $member->getFirstAttribute('uid'),
                    'cn' => $member->getFirstAttribute('cn'),
                ];
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
