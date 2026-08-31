<?php

namespace App\Livewire;

use App\Ldap\Community;
use App\Models\RealmBranding;
use App\Rules\DomainRegistrationRule;
use App\Rules\UniqueEmail;
use App\Support\LdapAccountRegistrar;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class RegisterUser extends Component
{
    // public User $user;

    #[Locked]
    public string $realm_uid;

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

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->realm_uid = $realm->getShortCode();
    }

    protected function rules(): array
    {
        $community = Community::findOrFailByUid($this->realm_uid);

        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail($community),
            ],
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
            'domain' => [
                'required',
                new DomainRegistrationRule($community),
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
        $branding = RealmBranding::forRealm($this->realm_uid);

        return view('livewire.register-user', ['branding' => $branding])
            ->layout('layouts.guest', ['branding' => $branding])
            ->title(__('user.register'));
    }

    public function save()
    {
        $this->validateDomain();
        $this->validate();
        $community = Community::findOrFailByUid($this->realm_uid);

        try {
            $eloquentUser = resolve(LdapAccountRegistrar::class)->register(
                $community,
                $this->username,
                $this->first_name,
                $this->last_name,
                $this->email,
                $this->password,
            );

            // Fired with the Eloquent user (not the LDAP one) since only it
            // implements MustVerifyEmail/Notifiable, which the
            // SendEmailVerificationNotification listener requires to send.
            //
            // ->afterResponse(): neither that listener nor the VerifyEmail
            // notification is queued, so without this, the redirect below
            // would wait on a real SMTP round-trip (MAIL_MAILER=smtp) -
            // QUEUE_CONNECTION is "sync" everywhere with no worker running
            // (same reasoning as App\Support\EndsAuthenticatedSession).
            dispatch(function () use ($eloquentUser): void {
                event(new Registered($eloquentUser));
            })->afterResponse();

            return to_route('realm.login', ['realm' => $this->realm_uid])->with('status', __('user.registration_successful_verify_email'));

        } catch (LdapRecordException $ldapRecordException) {
            report($ldapRecordException);
            $this->addError('username', $ldapRecordException->getDetailedError()?->getErrorMessage() ?? __('user.error.registration_failed'));
        }
    }
}
