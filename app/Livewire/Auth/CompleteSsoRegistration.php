<?php

namespace App\Livewire\Auth;

use App\Ldap\Community;
use App\Ldap\User;
use App\Models\RealmBranding;
use App\Models\RealmSsoProvider;
use App\Providers\RouteServiceProvider;
use App\Support\RealmContext;
use App\Support\SsoGroupRoleSync;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CompleteSsoRegistration extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public ?int $provider_id = null;

    #[Locked]
    public string $email = '';

    #[Validate('required|string|min:3|max:255|regex:/^[0-9a-z_\-\.]*$/')]
    public string $username = '';

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->realm_uid = $realm->getShortCode();

        $pending = $this->pending();

        $this->provider_id = $pending['provider_id'];
        $this->email = $pending['email'];
        $this->first_name = $pending['given_name'] ?? '';
        $this->last_name = $pending['family_name'] ?? '';
    }

    protected function pending(): array
    {
        $pending = session('sso_pending');

        abort_unless(
            is_array($pending) && ($pending['realm'] ?? null) === $this->realm_uid,
            404
        );

        return $pending;
    }

    public function render()
    {
        $branding = RealmBranding::forRealm($this->realm_uid);

        return view('livewire.auth.complete-sso-registration', ['branding' => $branding])
            ->layout('layouts.guest', ['branding' => $branding])
            ->title(__('sso_providers.complete_registration_title'));
    }

    public function save()
    {
        $this->validate();

        $pending = $this->pending();
        $community = Community::findOrFailByUid($this->realm_uid);
        $randomPassword = Str::random(40);

        $user = new User([
            'uid' => $this->username,
            'cn' => $this->first_name.' '.$this->last_name,
            'sn' => $this->last_name,
            'givenName' => $this->first_name,
            'mail' => $pending['email'],
            'userPassword' => '{ARGON2}'.password_hash($randomPassword, PASSWORD_ARGON2ID),
        ]);
        $user->setDn("uid=$this->username,".$community->peopleDn());

        try {
            $user->save();
            $user->refresh();

            app(RealmContext::class)->set($community);
            Auth::validate(['uid' => $this->username, 'password' => $randomPassword]);

            $eloquentUser = \App\Models\User::where('uid', $user->getConvertedGuid())->first();
            $eloquentUser->forceFill([
                'realm' => $community->getFirstAttribute('ou'),
                'email_verified_at' => now(),
            ])->save();

            Auth::login($eloquentUser);
            session()->regenerate();
            session()->forget('sso_pending');

            $provider = RealmSsoProvider::find($pending['provider_id']);
            if ($provider) {
                app(SsoGroupRoleSync::class)->apply($provider, $this->username, $pending['claims'] ?? []);
            }

            return redirect()->intended(RouteServiceProvider::home($this->realm_uid));
        } catch (LdapRecordException $ldapRecordException) {
            report($ldapRecordException);
            $this->addError('username', $ldapRecordException->getDetailedError()?->getErrorMessage() ?? __('user.error.registration_failed'));
        }
    }
}
