<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use App\Rules\UniqueEmail;
use App\Support\InvitationMailer;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InviteUser extends Component
{
    #[Locked]
    public string $uid;

    #[Validate]
    public string $email = '';

    public string $selected_committee_dn = '';

    public string $selected_role_dn = '';

    /** @var array<string, array{committee_dn: string, role_cn: string, label: string}> keyed by "{committee_dn}|{role_cn}" */
    public array $queuedRoleSelections = [];

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
    }

    protected function rules(): array
    {
        $community = Community::findOrFailByUid($this->uid);

        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail($community),
            ],
        ];
    }

    public function render()
    {
        $committees = Committee::fromCommunity($this->uid)->recursive()->get();

        $roles = collect();
        if (! empty($this->selected_committee_dn)) {
            $roles = $this->findRealmCommittee($this->selected_committee_dn)?->roles()->get() ?? collect();
        }

        return view('livewire.tools.invite-user', [
            'committees' => $committees,
            'roles' => $roles,
        ])->title(__('tools.invite_user_headline'));
    }

    public function updatedSelectedCommitteeDn(): void
    {
        $this->reset('selected_role_dn');
    }

    /**
     * Adds the currently selected committee+role pair to the queue that
     * save() will turn into InvitationRoleSelection rows - lets the admin
     * build up any number of Gremien/Rollen combinations (not just roles
     * within a single committee) before sending the invitation.
     */
    public function addRoleSelection(): void
    {
        if (empty($this->selected_committee_dn) || empty($this->selected_role_dn)) {
            return;
        }

        $committee = $this->findRealmCommittee($this->selected_committee_dn);

        if (! $committee) {
            $this->reset('selected_committee_dn', 'selected_role_dn');

            return;
        }

        // The role select is only ever populated from this exact committee's
        // own roles() (see render()), but the submitted value is still
        // client-controlled - confirm it actually resolves to one of them
        // rather than trusting the DN string as-is.
        $role = $committee->roles()->get()->first(fn ($role) => $role->getDn() === $this->selected_role_dn);

        if (! $role) {
            $this->addError('selected_role_dn', __('tools.invalid_role_selection'));

            return;
        }

        $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');

        if (isset($this->queuedRoleSelections[$key])) {
            $this->addError('selected_role_dn', __('tools.role_already_added'));

            return;
        }

        $committeeLabel = $committee->getFirstAttribute('description') ?: $committee->getFirstAttribute('ou');
        $roleLabel = $role->getFirstAttribute('description') ?: $role->getFirstAttribute('cn');

        $this->queuedRoleSelections[$key] = [
            'committee_dn' => $committee->getDn(),
            'role_cn' => $role->getFirstAttribute('cn'),
            'label' => "{$committeeLabel} › {$roleLabel}",
        ];

        $this->reset('selected_role_dn');
    }

    public function removeRoleSelection(string $key): void
    {
        unset($this->queuedRoleSelections[$key]);
    }

    /**
     * Resolves a committee DN submitted from the form. The DN suffix check
     * has to happen before Committee::find() is ever called with it -
     * Committee has no global scope limiting reads to one realm's own
     * branch (unlike App\Ldap\Community), so find() would otherwise happily
     * resolve a DN belonging to a different realm's committees, or an
     * unrelated LDAP entry entirely, if a tampered value were trusted as-is.
     */
    private function findRealmCommittee(string $committeeDn): ?Committee
    {
        if (! str_ends_with($committeeDn, ','.Committee::dnRootResolved($this->uid))) {
            return null;
        }

        return Committee::find($committeeDn);
    }

    public function save()
    {
        $this->validate();

        $community = Community::findOrFailByUid($this->uid);

        $invitation = Invitation::create([
            'realm' => $this->uid,
            'email' => $this->email,
            'invited_by_username' => auth()->user()->username,
            'expires_at' => Invitation::freshExpiry(),
        ]);

        foreach ($this->queuedRoleSelections as $selection) {
            InvitationRoleSelection::create([
                'invitation_id' => $invitation->id,
                'committee_dn' => $selection['committee_dn'],
                'role_cn' => $selection['role_cn'],
            ]);
        }

        resolve(InvitationMailer::class)->send($invitation, $community);

        Flux::toast(variant: 'success', text: __('tools.invitation_sent', ['email' => $invitation->email]));

        return to_route('tools.invitations', ['realm' => $this->uid]);
    }
}
