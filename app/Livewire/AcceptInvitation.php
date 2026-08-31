<?php

namespace App\Livewire;

use App\Ldap\Community;
use App\Models\Invitation;
use App\Models\RealmBranding;
use App\Models\RoleMembership;
use App\Support\LdapAccountRegistrar;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AcceptInvitation extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public int $invitation_id;

    #[Locked]
    public string $email;

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('required|string|min:3|max:255|regex:/^[0-9a-z_\-\.]*$/')]
    public string $username = '';

    #[Validate]
    public string $password = '';

    #[Validate]
    public string $password_confirmation = '';

    /**
     * The `signed` middleware already guarantees realm/invitation/hash
     * weren't tampered with and haven't expired (they're all part of the
     * signature), but the checks below are repeated anyway - the same
     * redundancy Laravel's own VerifyEmailController applies to its
     * signed id/hash - so a stale or already-used link still gets a clean
     * 404/403 here rather than relying solely on the signature layer.
     */
    public function mount(Community $realm, Invitation $invitation, string $hash): void
    {
        abort_if($realm->isAdminRealm(), 404);
        abort_unless(hash_equals(sha1($invitation->email), $hash), 403);
        abort_unless($invitation->realm === $realm->getShortCode(), 404);
        abort_if($invitation->accepted_at !== null, 404);

        $this->realm_uid = $realm->getShortCode();
        $this->invitation_id = $invitation->id;
        $this->email = $invitation->email;

        $localPart = explode('@', $this->email)[0] ?? '';
        $this->username = str_replace(['-', '_', '.'], '', $localPart);
    }

    protected function rules(): array
    {
        return [
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
        ];
    }

    public function render()
    {
        $branding = RealmBranding::forRealm($this->realm_uid);

        return view('livewire.accept-invitation', ['branding' => $branding])
            ->layout('layouts.guest', ['branding' => $branding])
            ->title(__('invitations.accept_title'));
    }

    public function save()
    {
        $this->validate();

        $community = Community::findOrFailByUid($this->realm_uid);

        // Re-fetched (not the one resolved in mount()) and re-scoped by
        // realm + not-yet-accepted, so a second concurrent submission of the
        // same link can't create the account - and its role memberships -
        // twice.
        $invitation = Invitation::where('id', $this->invitation_id)
            ->where('realm', $this->realm_uid)
            ->whereNull('accepted_at')
            ->firstOrFail();

        try {
            $eloquentUser = resolve(LdapAccountRegistrar::class)->register(
                $community,
                $this->username,
                $this->first_name,
                $this->last_name,
                $invitation->email,
                $this->password,
            );

            // The invitee just proved control of this mailbox by following a
            // signed, address-specific, time-limited link - stronger proof
            // than App\Livewire\Realm\NewMember's unconditional
            // email_verified_at, which has none at all. No further
            // verification round-trip (and no Registered event) needed.
            // markEmailAsVerified() (Illuminate\Auth\MustVerifyEmail) is
            // required rather than a plain update() - email_verified_at is
            // deliberately not mass-assignable.
            $eloquentUser->markEmailAsVerified();

            foreach ($invitation->roleSelections as $selection) {
                RoleMembership::create([
                    'role_cn' => $selection->role_cn,
                    'committee_dn' => $selection->committee_dn,
                    'realm' => $this->realm_uid,
                    'username' => $this->username,
                    'from' => today(),
                    'decided' => today(),
                    'comment' => __('invitations.role_membership_comment'),
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            return to_route('realm.login', ['realm' => $this->realm_uid])->with('status', __('invitations.accept_success'));

        } catch (LdapRecordException $ldapRecordException) {
            report($ldapRecordException);
            $this->addError('username', $ldapRecordException->getDetailedError()?->getErrorMessage() ?? __('user.error.registration_failed'));
        }
    }
}
