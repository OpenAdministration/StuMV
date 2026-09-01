<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ListInvitations extends Component
{
    #[Locked]
    public string $uid;

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
    }

    public function render()
    {
        $pending = Invitation::where('realm', $this->uid)
            ->whereNull('accepted_at')
            ->with('roleSelections')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.tools.list-invitations', [
            'pending' => $pending,
        ])->title(__('tools.pending_invitations_headline'));
    }

    /**
     * @return array<int, string> "Committee › Role" labels for one invitation's staged selections
     */
    public function roleLabelsFor(Invitation $invitation): array
    {
        return $invitation->roleSelections
            ->map(function (InvitationRoleSelection $selection): string {
                $committee = Committee::find($selection->committee_dn);
                $role = $committee?->roles()->where('cn', $selection->role_cn)->first();

                $committeeLabel = $committee
                    ? ($committee->getFirstAttribute('description') ?: $committee->getFirstAttribute('ou'))
                    : $selection->committee_dn;
                $roleLabel = $role
                    ? ($role->getFirstAttribute('description') ?: $role->getFirstAttribute('cn'))
                    : $selection->role_cn;

                return "{$committeeLabel} › {$roleLabel}";
            })
            ->all();
    }

    public function revoke(int $invitationId): void
    {
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('tools', $community);

        // Scoped by realm, not just id - an invitation id alone must never
        // be enough to revoke another realm's invitation.
        Invitation::where('id', $invitationId)->where('realm', $this->uid)->delete();

        Flux::toast(variant: 'success', text: __('tools.invitation_revoked'));
    }
}
