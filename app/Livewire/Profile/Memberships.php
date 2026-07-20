<?php

namespace App\Livewire\Profile;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\RoleMembership;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * A plain realm parameter (not $this->realm_uid) so this is safely
     * callable on an instance resolved outside the normal Livewire
     * lifecycle (e.g. ListMembers::exportPdf() via resolve(Memberships::class)),
     * which never runs mount().
     */
    public function getMemberships(string $realmUid, string $username, bool $onlyActive)
    {
        // committee_dn is a bare string column (no realm reference of its
        // own) - filter by which realm's Committees branch it falls under
        // in addition to username, since the same username can now
        // independently exist in more than one realm.
        $query = RoleMembership::where('username', $username)
            ->where('committee_dn', 'like', '%'.Committee::dnRootResolved($realmUid));
        if ($onlyActive) {
            $query->whereNull('until');
        }
        $roleMemberships = $query->get();
        $memberships = [];
        foreach ($roleMemberships as $row) {
            $role = Role::findOrFail('cn='.$row->role_cn.','.$row->committee_dn);
            $memberships[] = [
                'role' => $role,
                'from' => $row->from,
                'until' => $row->until,
                'decided' => $row->decided,
                'comment' => $row->comment,
            ];
        }

        return $memberships;
    }

    public function render()
    {
        $memberships = $this->getMemberships($this->realm_uid, $this->currentUsername, $this->showOnlyActive);
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
        $memberships = $this->getMemberships($this->realm_uid, $this->currentUsername, false);
        $user = User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);
        $pdf = Pdf::loadView('pdfs.memberships', [
            'fullName' => $user->cn[0],
            'community' => null,
            'memberships' => $memberships,
        ]);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->stream();
        }, strtolower(trans('profile.memberships')).'_'.$this->currentUsername.'.pdf');
    }
}
