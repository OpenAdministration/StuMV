<?php

namespace App\Livewire;

use App\Ldap\Domain;
use App\Ldap\User;
use App\Rules\DomainRegistrationRule;
use App\Rules\UniqueEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class RegisterUser extends Component
{
    // public User $user;

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

    public string $domain = '';

    protected function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail,
            ],
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
            'domain' => [
                'required',
                new DomainRegistrationRule,
            ],
        ];
    }

    /**
     * Do some stuff if email was changed
     */
    public function updatedEmail(): void
    {
        $this->validateDomain();
        $this->preFillFromMail();
    }

    public function preFillFromMail()
    {
        $split = explode('@', $this->email);
        $this->username = str_replace(['-', '_', '.'], '', $split[0] ?? $this->username ?? '');
        if (str_contains($split[0], '.')) {
            $nameParts = explode('.', $split[0], 2);
            $this->first_name = ucwords(str_replace(['-', '_'], ' ', $nameParts[0] ?? ''));
            $this->last_name = str_replace(' ', '-', ucwords(str_replace(['-', '_'], ' ', $nameParts[1] ?? '')));
        } else {
            $guessedName = explode(' ', ucwords(str_replace(['-', '_', '.'], ' ', $split[0])), 2);
            $this->first_name = $guessedName[0] ?? $this->first_name ?? '';
            $this->last_name = $guessedName[1] ?? $this->last_name ?? '';
        }
        $this->validateOnly('username');
    }

    public function validateDomain()
    {
        $this->validateOnly('email');
        $split = explode('@', $this->email);
        $this->domain = $split[1] ?? 'false';
        $this->validateOnly('domain');
    }

    public function render()
    {
        return view('livewire.register-user')
            ->layout('layouts.guest')
            ->title(__('user.register'));
    }

    public function save()
    {
        $this->validateDomain();
        $this->validate();
        $domain = Domain::findByOrFail('dc', $this->domain);
        $community = $domain->community();
        $user = new User([
            'uid' => $this->username,
            'cn' => $this->first_name.' '.$this->last_name,
            'sn' => $this->last_name,
            'givenName' => $this->first_name,
            'mail' => $this->email,
            'userPassword' => '{ARGON2}'.password_hash($this->password, PASSWORD_ARGON2ID),
            // usually ldap SHOULD hash it itself - did not work
        ]);
        $user->setDn("uid=$this->username,ou=People,{base}");
        try {
            $user->save();
            $community->membersGroup()->members()->attach($user);
            // Credentials must be keyed for the LDAP guard (see LoginRequest);
            // a positional array does not authenticate.
            Auth::attempt(['uid' => $this->username, 'password' => $this->password]);

            $eloquentUser = \App\Models\User::where('username', $this->username)->first();
            $eloquentUser->update([
                'realm' => $community->getFirstAttribute('ou'),
            ]);

            // Fired with the Eloquent user (not the LDAP one) since only it
            // implements MustVerifyEmail/Notifiable, which the
            // SendEmailVerificationNotification listener requires to send.
            event(new Registered($eloquentUser));

            return to_route('verification.notice')->with('message', __('Successfully Registered'));

        } catch (LdapRecordException $ldapRecordException) {
            dump($ldapRecordException->getDetailedError());
        }
    }
}
