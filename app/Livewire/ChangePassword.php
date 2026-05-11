<?php

namespace App\Livewire;

use App\Ldap\User;
use App\Providers\RouteServiceProvider;
use Flux\Flux;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mockery\Generator\StringManipulation\Pass\Pass;

class ChangePassword extends Component
{
    public string $password;

    public string $password_confirmation;

    #[Locked]
    public $currentUsername;

    public function mount($username)
    {
        if ($username === auth()->user()->username || auth()->user()->can('superadmin', User::class)) {
            $this->currentUsername = $username;
        } elseif ($username === auth()->user()->username) {
            $this->currentUsername = auth()->user()->username;
        } else {
            abort('403');
        }
    }

    public function rules(): array
    {
        return [
            'password' => [Password::default(), 'confirmed']
        ];
    }
    public function render()
    {
        $user = User::findOrFailByUsername($this->currentUsername);
        $givenName = $user->getFirstAttribute('givenName');
        $sn = $user->getFirstAttribute('sn');

        return view('livewire.change-password', [
            'givenName' => $givenName,
            'sn' => $sn,
        ])->title(__('Change Password'));
    }

    public function save()
    {
        $this->validate();
        $ldapUser = User::findOrFailByUsername($this->currentUsername);
        $ldapUser->setAttribute('userPassword', "{ARGON2}" . password_hash($this->password, PASSWORD_ARGON2ID));
        $ldapUser->save();

        Flux::toast(variant: 'success', text: __('Password has been changed'));
        return redirect(RouteServiceProvider::home());
    }
}
