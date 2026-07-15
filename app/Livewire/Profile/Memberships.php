<?php

namespace App\Livewire\Profile;

use App\Ldap\Role;
use App\Ldap\User;
use App\Models\RoleMembership;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Memberships extends Component
{
    #[Locked]
    public $currentUsername;

    public bool $showOnlyActive = true;

    public function mount($username)
    {
        $this->authorize('manageProfile', [User::class, $username]);
        $this->currentUsername = $username;
    }

    public function getMemberships(string $username, bool $onlyActive)
    {
        $query = RoleMembership::where('username', $username);
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
        $memberships = $this->getMemberships($this->currentUsername, $this->showOnlyActive);
        $user = User::findOrFailByUsername($this->currentUsername);
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
        $memberships = $this->getMemberships($this->currentUsername, false);
        $user = User::findOrFailByUsername($this->currentUsername);
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
