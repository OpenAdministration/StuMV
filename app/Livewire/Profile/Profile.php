<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Models\UserAdditionalEmail;
use App\Rules\UniqueEmail;
use App\Support\AdditionalEmailMailer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Profile extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $uid;

    #[Locked]
    public string $email;

    /**
     * Further values of the same LDAP "mail" attribute the primary address
     * lives in (see App\Ldap\User). They exist solely so a login through an
     * external identity provider can find this account when the provider
     * asserts one of them instead of the primary.
     *
     * @var array<int, string>
     */
    public array $additionalEmails = [];

    /** Set by the save below so the toast can mention the confirmation mail. */
    private bool $pendingVerificationSent = false;

    #[Rule('string|required')]
    public string $givenName;

    #[Rule('string|required')]
    public string $sn;

    public $course;

    public $street;

    public $postalCode;

    public $city;

    public $phone;

    public $currentUsername;

    public $userIsActive = true;

    public function mount(Community $realm, $username)
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
        $user = $this->findUserWithLockStatus($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');
        $this->givenName = $user->getFirstAttribute('givenName');
        $this->sn = $user->getFirstAttribute('sn');
        $this->email = $user->getFirstAttribute('mail');
        $this->adoptLdapAddressesWithoutRow($user);
        $this->additionalEmails = $this->additionalEmailRows()->pluck('address')->all();
        $this->course = $user->getFirstAttribute('description');
        $this->street = $user->getFirstAttribute('street');
        $this->postalCode = $user->getFirstAttribute('postalCode');
        $this->city = $user->getFirstAttribute('l');
        $this->phone = $user->getFirstAttribute('telephoneNumber');

        if ($user->hasAttribute('pwdAccountLockedTime') && $user->getFirstAttribute('pwdAccountLockedTime') === '00000101000000Z') {
            $this->userIsActive = false;
        } else {
            $this->userIsActive = true;
        }
    }

    public function render()
    {
        return view('livewire.profile.profile', [
            'verifiedAddresses' => $this->additionalEmailRows()->verified()->pluck('address')->all(),
        ])->title(__('profile.title', ['name' => $this->givenName.' '.$this->sn]));
    }

    /** @return Builder<UserAdditionalEmail> */
    private function additionalEmailRows(): Builder
    {
        return UserAdditionalEmail::query()
            ->forAccount($this->currentUsername, $this->realm_uid)
            ->orderBy('address');
    }

    /** Adopts addresses added to LDAP outside this form, so save() keeps them. */
    private function adoptLdapAddressesWithoutRow(User $user): void
    {
        $known = $this->additionalEmailRows()->pluck('address')->all();

        foreach (array_diff($user->additionalEmails(), $known) as $address) {
            UserAdditionalEmail::create([
                'username' => $this->currentUsername,
                'realm' => $this->realm_uid,
                'address' => $address,
                'verified_at' => now(),
            ]);
        }
    }

    public function addEmailRow(): void
    {
        $this->additionalEmails[] = '';
    }

    public function removeEmailRow(int $index): void
    {
        unset($this->additionalEmails[$index]);
        $this->additionalEmails = array_values($this->additionalEmails);
    }

    /**
     * Validated separately from the #[Rule] attributes above, which
     * $this->validate() handles on its own - a rules array passed to it
     * replaces those rather than adding to them.
     */
    private function validateAdditionalEmails(): void
    {
        // Rows added but never filled in are simply dropped, so "add" followed
        // by "save" isn't an error.
        $this->additionalEmails = array_values(array_filter(
            array_map(trim(...), $this->additionalEmails),
            fn (string $address): bool => $address !== ''
        ));

        $this->validate([
            'additionalEmails.*' => [
                'email',
                'max:255',
                'distinct',
                ValidationRule::notIn([$this->email]),
                new UniqueEmail(Community::findOrFailByUid($this->realm_uid), $this->uid),
            ],
        ], [
            'additionalEmails.*.not_in' => __('profile.emails_error_is_primary'),
            'additionalEmails.*.distinct' => __('profile.emails_error_duplicate'),
        ]);
    }

    /**
     * New addresses are only recorded unverified and mailed a link - they
     * reach LDAP once that link is followed, never here.
     *
     * @return array<int, string> the confirmed addresses, for LDAP
     */
    private function reconcileAdditionalEmails(): array
    {
        $rows = $this->additionalEmailRows()->get();
        $community = Community::findOrFailByUid($this->realm_uid);

        $rows->reject(fn (UserAdditionalEmail $row): bool => in_array($row->address, $this->additionalEmails, true))
            ->each(fn (UserAdditionalEmail $row) => $row->delete());

        foreach (array_diff($this->additionalEmails, $rows->pluck('address')->all()) as $address) {
            $row = UserAdditionalEmail::create([
                'username' => $this->currentUsername,
                'realm' => $this->realm_uid,
                'address' => $address,
            ]);

            resolve(AdditionalEmailMailer::class)->sendVerification($row, $community);
            $this->pendingVerificationSent = true;
        }

        return $this->additionalEmailRows()->verified()->pluck('address')->all();
    }

    public function save()
    {
        $this->validate();
        $this->validateAdditionalEmails();
        $verifiedAddresses = $this->reconcileAdditionalEmails();
        $user = $this->findUserWithLockStatus($this->uid);
        // The primary stays in first position - App\Ldap\User relies on that
        // to tell it apart from the additional addresses.
        $user->setAttribute('mail', [$this->email, ...$verifiedAddresses]);
        $user->setAttribute('givenName', $this->givenName);
        $user->setAttribute('sn', $this->sn);
        $user->setAttribute('cn', $this->givenName.' '.$this->sn);
        $user->setAttribute('description', $this->course);
        $user->setAttribute('street', $this->street);
        $user->setAttribute('postalCode', $this->postalCode);
        $user->setAttribute('l', $this->city);
        $user->setAttribute('telephoneNumber', $this->phone);

        $isCurrentlyLocked = $user->hasAttribute('pwdAccountLockedTime');

        if ($this->userIsActive === $isCurrentlyLocked) {
            abort_unless(auth()->user()->can('superadmin', \App\Models\User::class), 403);
        }

        if ($this->userIsActive && $isCurrentlyLocked) {
            $user->removeAttribute('pwdAccountLockedTime');
        } elseif (! $this->userIsActive) {
            $user->setAttribute('pwdAccountLockedTime', '00000101000000Z');
        }

        $user->save();

        \App\Models\User::where('uid', $user->getConvertedGuid())->update([
            'full_name' => $this->givenName.' '.$this->sn,
        ]);

        Flux::toast(variant: 'success', text: $this->pendingVerificationSent
            ? __('profile.emails_verification_sent')
            : __('common.saved'));
        $this->redirect(route('profile', ['realm' => $this->realm_uid, 'username' => $this->uid]), navigate: true);
    }

    /**
     * pwdAccountLockedTime is an operational attribute: the LDAP server only
     * returns it when explicitly named in the select, never via a plain "*"
     * fetch. Without this, the account-active status can never be read back.
     */
    protected function findUserWithLockStatus(string $username): User
    {
        return User::query()
            ->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())
            ->select(['*', 'pwdAccountLockedTime'])
            ->where('uid', '=', $username)
            ->first() ?? abort(404);
    }
}
