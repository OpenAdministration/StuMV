<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use App\Providers\RouteServiceProvider;
use Flux\Flux;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ChangePassword extends Component
{
    #[Validate]
    public string $password;

    #[Validate]
    public string $password_confirmation;

    #[Locked]
    public $currentUsername;

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

    public function mount($username)
    {
        $this->authorize('manageProfile', [User::class, $username]);
        $this->currentUsername = $username;
    }

    public function render()
    {
        $user = User::findOrFailByUsername($this->currentUsername);
        $givenName = $user->getFirstAttribute('givenName');
        $sn = $user->getFirstAttribute('sn');

        return view('livewire.profile.change-password', [
            'givenName' => $givenName,
            'sn' => $sn,
        ])->title(__('profile.change_password_title'));
    }

    public function save()
    {
        $this->validate();
        $ldapUser = User::findOrFailByUsername($this->currentUsername);
        $ldapUser->setAttribute('userPassword', '{ARGON2}'.password_hash($this->password, PASSWORD_ARGON2ID));
        $ldapUser->save();

        Flux::toast(variant: 'success', text: __('Password has been changed'));

        return redirect(RouteServiceProvider::home());
    }
}
