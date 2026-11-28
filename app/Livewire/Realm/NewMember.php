<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use App\Rules\UniqueEmail;
use App\Support\DatabaseUserProvisioner;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * A realm-admin-facing registration form: unlike self-registration
 * (App\Livewire\RegisterUser), this creates a brand-new account directly
 * under the admin's own realm and deliberately skips domain-registration
 * checking - an admin adding someone here is a trusted, manual action, not
 * an unsupervised public signup that needs gating by email domain.
 */
class NewMember extends Component
{
    public string $email = '';

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

    public string $realm_uid = '';

    public function mount(Community $realm): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail(Community::findOrFailByUid($this->realm_uid)),
            ],
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.realm.new-member')
            ->title(__('realms.new_member_title', ['realm' => $this->realm_uid]));
    }

    /** @return false|RedirectResponse */
    public function save()
    {
        $this->validate();

        $realm = Community::findOrFailByUid($this->realm_uid);

        $user = new User([
            'uid' => $this->username,
            'cn' => $this->first_name.' '.$this->last_name,
            'sn' => $this->last_name,
            'givenName' => $this->first_name,
            'mail' => $this->email,
            'userPassword' => '{ARGON2}'.password_hash($this->password, PASSWORD_ARGON2ID),
        ]);
        $user->setDn("uid=$this->username,".$realm->peopleDn());

        try {
            $user->save();

            // entryUUID is server-assigned and not part of the in-memory
            // model right after an insert - refresh so getConvertedGuid()
            // below actually returns it.
            $user->refresh();
            $user->recordAction('member.created', $this->realm_uid);

            DatabaseUserProvisioner::ensure($realm, $user)->markEmailAsVerified();
        } catch (LdapRecordException $ldapRecordException) {
            report($ldapRecordException);
            $this->addError('username', $ldapRecordException->getDetailedError()?->getErrorMessage() ?? __('user.error.registration_failed'));

            return false;
        }

        Flux::toast(variant: 'success', text: __('realms.added_new_member'));

        return to_route('realms.members', ['realm' => $this->realm_uid]);
    }
}
