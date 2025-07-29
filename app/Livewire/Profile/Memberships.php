<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use App\Ldap\Role;
use App\Models\RoleMembership;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Component;

class Memberships extends Component
{
    public $currentUsername;
    public bool $showOnlyActive = true;

    public function mount($username)
    {
        if ($username == auth()->user()->username || auth()->user()->can('superadmin', User::class)) {
            $this->currentUsername = $username;
        } elseif ($username == auth()->user()->username) {
            $this->currentUsername = auth()->user()->username;
        } else {
            abort('403');
        }
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
            $role = Role::findOrFail('cn=' . $row->role_cn . ',' . $row->committee_dn);
            array_push($memberships, [
                'role' => $role,
                'from' => $row->from,
                'until' => $row->until,
                'decided' => $row->decided,
                'comment' => $row->comment,
            ]);
        }
        return $memberships;
    }

    public function render()
    {
        $memberships = $this->getMemberships($this->currentUsername, $this->showOnlyActive);

        return view('livewire.profile.memberships', [
            'memberships' => $memberships,
        ])->title(__('Profile'));
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

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'memberships-' . $this->currentUsername . '.pdf');;
    }
}
