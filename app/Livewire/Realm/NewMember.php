<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use App\Rules\UniqueEmail;
use Flux\Flux;
use Illuminate\Support\Str;
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

    public function render()
    {
        return view('livewire.realm.new-member')
            ->title(__('realms.new_member_title', ['realm' => $this->realm_uid]));
    }

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

            \App\Models\User::updateOrCreate(
                ['uid' => $user->getConvertedGuid()],
                [
                    'username' => $this->username,
                    'full_name' => $this->first_name.' '.$this->last_name,
                    'email' => $this->email,
                    'email_verified_at' => now(),
                    'password' => password_hash(Str::random(40), PASSWORD_ARGON2ID),
                    'realm' => $this->realm_uid,
                ],
            );
        } catch (LdapRecordException $ldapRecordException) {
            report($ldapRecordException);
            $this->addError('username', $ldapRecordException->getDetailedError()?->getErrorMessage() ?? __('user.error.registration_failed'));

            return false;
        }

        Flux::toast(variant: 'success', text: __('realms.added_new_member'));

        return to_route('realms.members', ['realm' => $this->realm_uid]);
    }
}
