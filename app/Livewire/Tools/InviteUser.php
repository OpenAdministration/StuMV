<?php

namespace App\Livewire\Tools;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use App\Notifications\UserInvitation;
use App\Rules\CommitteeRoleSelection;
use App\Rules\UniqueEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InviteUser extends Component
{
    #[Locked]
    public string $uid;

    #[Validate]
    public string $email = '';

    /** @var list<string> "{committee_dn}|{role_cn}" pillbox values */
    public array $roleSelections = [];

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
            'roleSelections.*' => [
                new CommitteeRoleSelection($this->uid),
            ],
        ];
    }

    public function render()
    {
        $pending = Invitation::where('realm', $this->uid)
            ->whereNull('accepted_at')
            ->with('roleSelections')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.tools.invite-user', [
            'roleOptions' => $this->roleOptions(),
            'pending' => $pending,
        ])->title(__('tools.invite_user_headline'));
    }

    /**
     * @return array<string, string> "{committee_dn}|{role_cn}" => "Committee › Role"
     */
    protected function roleOptions(): array
    {
        $options = [];

        foreach (Committee::fromCommunity($this->uid)->list()->get() as $committee) {
            $committeeLabel = $committee->getFirstAttribute('description') ?: $committee->getFirstAttribute('ou');

            foreach ($committee->roles()->get() as $role) {
                $roleLabel = $role->getFirstAttribute('description') ?: $role->getFirstAttribute('cn');
                $options[$committee->getDn().'|'.$role->getFirstAttribute('cn')] = "{$committeeLabel} › {$roleLabel}";
            }
        }

        return $options;
    }

    /**
     * @return array<int, string> "Committee › Role" labels for one invitation's staged selections
     */
    public function roleLabelsFor(Invitation $invitation): array
    {
        $options = $this->roleOptions();

        return $invitation->roleSelections
            ->map(fn (InvitationRoleSelection $selection): string => $options[$selection->committee_dn.'|'.$selection->role_cn]
                ?? $selection->committee_dn.' | '.$selection->role_cn)
            ->all();
    }

    public function save(): void
    {
        $this->validate();

        $community = Community::findOrFailByUid($this->uid);

        $invitation = Invitation::create([
            'realm' => $this->uid,
            'email' => $this->email,
            'invited_by_username' => auth()->user()->username,
            'expires_at' => now()->addDays(7),
        ]);

        foreach ($this->roleSelections as $selection) {
            [$committeeDn, $roleCn] = explode('|', $selection, 2);
            InvitationRoleSelection::create([
                'invitation_id' => $invitation->id,
                'committee_dn' => $committeeDn,
                'role_cn' => $roleCn,
            ]);
        }

        $url = URL::temporarySignedRoute('invitation.accept', $invitation->expires_at, [
            'realm' => $this->uid,
            'invitation' => $invitation->id,
            'hash' => sha1($invitation->email),
        ]);

        // Deferred like every other outbound mail in this app -
        // QUEUE_CONNECTION is "sync" with no worker, so sending inline would
        // block the response on a real SMTP round-trip (same reasoning as
        // App\Livewire\RegisterUser::save()'s Registered event).
        dispatch(function () use ($invitation, $url, $community): void {
            Notification::route('mail', $invitation->email)
                ->notify(new UserInvitation($invitation, $url, $community->getLongName()));
        })->afterResponse();

        $this->reset('email', 'roleSelections');

        Flux::toast(variant: 'success', text: __('tools.invitation_sent', ['email' => $invitation->email]));
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
