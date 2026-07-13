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

    public function mount(Community $uid, string $ou): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
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
        $this->authorize('moderator', [$committee, $community]);

        if (! $this->ready) {
            return view(
                'livewire.committee.list-committee-moderators', [
                    'committee' => $committee,
                    'committee_moderators' => collect(),
                ]
            )->title(__('committees.mods_heading', ['name' => $committee->getFirstAttribute('description')]));
        }

        $mods = $this->sortByName($committee->moderatorsGroup()->members()->get());

        return view(
            'livewire.committee.list-committee-moderators', [
                'committee' => $committee,
                'committee_moderators' => $mods,
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

    public function deleteCommit()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $this->authorize('moderator', [$committee, $community]);

        $user = User::findOrFailByUsername($this->deleteModeratorUsername);
        $committee->moderatorsGroup()->members()->detach($user);

        // Removing your own last claim to moderate this committee (or an
        // ancestor) means the render() authorize() check above would 403 on
        // the very next re-render - redirect away instead of leaving the
        // page to crash under its own removal.
        if (auth()->user()->cannot('moderator', [$committee, $community])) {
            return to_route('committees.roles', ['uid' => $this->realm_uid, 'ou' => $this->ou]);
        }

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
