<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Support\MembershipCertificate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Memberships extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public $currentUsername;

    public bool $showOnlyActive = true;

    public function mount(Community $realm, $username)
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    public function render()
    {
        $memberships = MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, $this->showOnlyActive);
        $user = User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);
        $givenName = $user->getFirstAttribute('givenName');
        $sn = $user->getFirstAttribute('sn');

        return view('livewire.profile.memberships', [
            'memberships' => $memberships,
            'givenName' => $givenName,
            'sn' => $sn,
        ])->title(__('profile.breadcrumb'));
    }

    public function exportPdf()
    {
        return MembershipCertificate::download(
            $this->realm_uid,
            $this->currentUsername,
            null,
            strtolower(trans('profile.memberships')).'_'.$this->currentUsername.'.pdf'
        );
    }
}
