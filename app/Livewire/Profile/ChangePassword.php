<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
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
    public string $realm_uid;

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

    public function mount(Community $realm, $username)
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    protected function findLdapUser(): User
    {
        return User::query()
            ->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())
            ->where('uid', '=', $this->currentUsername)
            ->first() ?? abort(404);
    }

    public function render()
    {
        $user = $this->findLdapUser();
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
        $ldapUser = $this->findLdapUser();
        $ldapUser->setAttribute('userPassword', '{ARGON2}'.password_hash($this->password, PASSWORD_ARGON2ID));
        $ldapUser->save();

        Flux::toast(variant: 'success', text: __('Password has been changed'));

        return redirect(RouteServiceProvider::home());
    }
}
