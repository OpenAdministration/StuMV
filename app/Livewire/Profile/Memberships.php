<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Concerns\SortsMemberships;
use App\Support\MembershipCertificate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Memberships extends Component
{
    use SortsMemberships;

    #[Locked]
    public string $realm_uid;

    /** @var string */
    #[Locked]
    public $currentUsername;

    public bool $showOnlyActive = true;

    public function mount(Community $realm, string $username): void
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    public function render(): View
    {
        $memberships = MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, $this->showOnlyActive);
        $user = User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);
        $givenName = $user->getFirstAttribute('givenName');
        $sn = $user->getFirstAttribute('sn');

        return view('livewire.profile.memberships', [
            'memberships' => $this->sortMemberships($memberships),
            // ended memberships can be exported even while hidden by the filter
            'hasMemberships' => $memberships !== [] || MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false) !== [],
            'givenName' => $givenName,
            'sn' => $sn,
        ])->title(__('profile.breadcrumb'));
    }
}
