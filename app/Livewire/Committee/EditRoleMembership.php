<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleMembership;
use App\Support\RoleMembershipLdapSync;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditRoleMembership extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Locked]
    public int $id;

    #[Locked]
    public string $username = '';

    #[Validate('date:Y-m-d')]
    public ?string $start_date = null;

    #[Validate('date:Y-m-d|nullable')]
    public ?string $end_date = null;

    #[Validate('date:Y-m-d|nullable')]
    public ?string $decision_date = null;

    #[Validate('string|nullable')]
    public string $comment = '';

    public function mount(Community $realm, $ou, $cn, $id)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->id = $id;
        $membership = RoleMembership::findOrFail($id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $this->authorize('edit', [$membership, $committee, $realm]);
        $this->username = $membership->username;
        $this->start_date = $membership->from?->format('Y-m-d');
        $this->end_date = $membership->until?->format('Y-m-d');
        $this->decision_date = $membership->decided?->format('Y-m-d');
        $this->comment = $membership->comment ?? '';
    }

    public function render()
    {
        return view('livewire.committee.edit-role-membership')
            ->title(__('roles.membership_edit_headline'));
    }

    public function save()
    {
        $this->validate();
        $membership = RoleMembership::findOrFail($this->id);
        $committee = Committee::findByName($this->uid, $this->ou);
        $community = Community::findOrFailByUid($this->uid);
        $this->authorize('edit', [$membership, $committee, $community]);
        $wasCurrent = $membership->isActive();
        $before = $membership->activityLogAttributes();
        $membership->update([
            'from' => $this->start_date,
            'until' => ! empty($this->end_date) ? $this->end_date : null,
            'decided' => ! empty($this->decision_date) ? $this->decision_date : null,
            'comment' => ! empty($this->comment) ? $this->comment : null,
        ]);
        $membership->recordAction('updated', $before);

        Flux::toast(variant: 'success', text: __('roles.edit_member_success', ['username' => $this->username, 'role' => $this->cn]));
        if (! resolve(RoleMembershipLdapSync::class)->sync($membership, $wasCurrent)) {
            Flux::toast(variant: 'warning', text: __('roles.ldap_sync_failed'));
        }

        return to_route('committees.roles.members', [
            'realm' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
            'id' => $this->id,
        ]);
    }
}
