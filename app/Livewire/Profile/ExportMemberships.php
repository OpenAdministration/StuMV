<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Concerns\SortsMemberships;
use App\Support\MembershipCertificate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ExportMemberships extends Component
{
    use SortsMemberships;

    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $currentUsername;

    /** @var array<int, string> */
    public array $selected = [];

    /** Where "cancel" leads: the profile or the member list this page was opened from. */
    #[Locked]
    public string $backUrl;

    public function mount(Community $realm, string $username): void
    {
        $this->authorizeExport($realm, $username);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;

        $fallback = route('profile.memberships', ['realm' => $this->realm_uid, 'username' => $username]);
        $previous = url()->previous();
        $this->backUrl = $previous !== url()->current() && str_starts_with($previous, url('/')) ? $previous : $fallback;

        $this->selectAll();
    }

    public function selectAll(): void
    {
        $this->selected = array_map(
            fn (array $row): string => (string) $row['id'],
            MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false),
        );
    }

    public function deselectAll(): void
    {
        $this->selected = [];
    }

    public function render()
    {
        $user = User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);

        return view('livewire.profile.export-memberships', [
            'memberships' => $this->sortMemberships(MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false)),
            'name' => $user->getFirstAttribute('givenName').' '.$user->getFirstAttribute('sn'),
        ])->title(__('profile.export_memberships_title'));
    }

    public function export()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $this->authorizeExport($community, $this->currentUsername);

        $this->validate(
            ['selected' => ['required', 'array', 'min:1']],
            ['selected.required' => __('profile.export_memberships_none_selected'), 'selected.min' => __('profile.export_memberships_none_selected')],
        );

        return MembershipCertificate::download(
            $this->realm_uid,
            $this->currentUsername,
            $community->getFirstAttribute('description'),
            strtolower(trans('profile.memberships')).'_'.$this->currentUsername.'.pdf',
            array_map(intval(...), $this->selected),
        );
    }

    /** The member themselves, their realm's admins, or its moderators (member list export). */
    private function authorizeExport(Community $realm, string $username): void
    {
        $user = auth()->user();

        abort_unless(
            $user->can('manageProfile', [User::class, $realm, $username]) || $user->can('moderator', $realm),
            403,
        );
    }
}
