<?php

namespace App\Livewire;

use App\Ldap\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mockery\Generator\StringManipulation\Pass\Pass;

class ChangePassword extends Component
{
    public string $password;

    public string $password_confirmation;

    public function rules(): array
    {
        return [
            'password' => [Password::default(), 'confirmed']
        ];
    }
    public function render()
    {
        return view('livewire.change-password');
    }

    public function save(){
        $this->validate();
        $username = Auth::user()->username;
        $ldapUser = User::findOrFailByUsername($username);
        $ldapUser->setAttribute('userPassword', "{ARGON2}" . Hash::make($this->password));
        $ldapUser->save();
        return redirect(RouteServiceProvider::HOME)->with('message', __('Password has been changed'));
    }
}
