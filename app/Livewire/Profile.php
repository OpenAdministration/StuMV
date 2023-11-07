<?php

namespace App\Livewire;

use App\Ldap\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Profile extends Component
{
    #[Rule('string|required')]
    public string $uid;

    #[Rule('string|required')]
    public string $fullName;

    #[Rule('email|required')]
    public string $email;

    public function mount()
    {
        $username = Auth::user()->username;
        $user = User::findOrFailByUsername($username);
        $this->uid = $user->getFirstAttribute('uid');
        $this->fullName = $user->getFirstAttribute('cn');
        $this->email = $user->getFirstAttribute('mail');
    }

    public function render()
    {
        return view('livewire.profile');
    }

    public function save()
    {
        $this->validate();
        if (Auth::user()->username !== $this->uid) {
            abort('500');
        }
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('mail', $this->email);
        $user->setAttribute('cn', $this->fullName);
        $user->save();
        return redirect()->route('profile')->with('message', __('Saved'));
    }
}
