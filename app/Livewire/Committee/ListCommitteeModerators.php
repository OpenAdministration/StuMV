<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ListCommitteeModerators extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $ou;

    public string $deleteModeratorName = '';

    public string $deleteModeratorUsername = '';

    public bool $ready = false;

    public function mount(Community $realm, string $ou): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    public function loadModerators(): void
    {
        $this->ready = true;
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        // Viewing who moderates a committee is open to any community member
        // (matching Realm\ListModerators at the community level) - only
        // adding/removing moderators is restricted, see deletePrepare()/
        // deleteCommit()/NewCommitteeModerator.
        $isModerator = auth()->user()->can('moderator', [$committee, $community]);

        if (! $this->ready) {
            return view(
                'livewire.committee.list-committee-moderators', [
                    'committee' => $committee,
                    'committee_moderators' => collect(),
                    'isModerator' => $isModerator,
                ]
            )->title(__('committees.mods_heading', ['name' => $committee->getFirstAttribute('description')]));
        }

        $mods = $this->sortByName($committee->moderatorsGroup()->members()->get());

        return view(
            'livewire.committee.list-committee-moderators', [
                'committee' => $committee,
                'committee_moderators' => $mods,
                'isModerator' => $isModerator,
            ]
        )->title(__('committees.mods_heading', ['name' => $committee->getFirstAttribute('description')]));
    }

    public function deletePrepare($username): void
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $this->authorize('moderator', [$committee, $community]);

        $user = User::findOrFailByUsername($username);
        $isModerator = $committee->moderatorsGroup()->members()->contains($user);
        if (! $isModerator) {
            return;
        }
        $this->deleteModeratorUsername = $username;
        $this->deleteModeratorName = $user->getFirstAttribute('cn');
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $this->authorize('moderator', [$committee, $community]);

        $user = User::findOrFailByUsername($this->deleteModeratorUsername);
        $committee->moderatorsGroup()->members()->detach($user);
        $this->close();
    }

    public function close(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteModeratorUsername, $this->deleteModeratorName);
    }

    /**
     * Sorted client-side (rather than via an LDAP orderBy) because the
     * production directory doesn't support the sssvlv sort control.
     */
    protected function sortByName(Collection $users): Collection
    {
        return $users
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute('cn')), SORT_NATURAL)
            ->values();
    }
}
