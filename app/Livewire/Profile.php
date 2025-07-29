<?php

namespace App\Livewire;

use App\Ldap\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Profile extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $email;

    #[Rule('string|required')]
    public string $fullName;

    public $currentUsername;

    public function mount($username)
    {
        if ($username == auth()->user()->username || auth()->user()->can('superadmin', User::class)) {
            $this->currentUsername = $username;
        } elseif ($username == auth()->user()->username) {
            $this->currentUsername = auth()->user()->username;
        } else {
            abort('403');
        }
        $user = User::findOrFailByUsername($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');
        $this->fullName = $user->getFirstAttribute('cn');
        $this->email = $user->getFirstAttribute('mail');
    }

    public function render()
    {
        return view('livewire.profile')->title(__('Profile'));
    }

    public function save()
    {
        $this->validate();
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('mail', $this->email);
        $user->setAttribute('cn', $this->fullName);
        $user->save();
        return redirect()->route('profile')->with('message', __('Saved'));
    }
}
