<?php

namespace App\Livewire;

use App\Ldap\User;
use Illuminate\Support\Str;
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
    public string $givenName;

    #[Rule('string|required')]
    public string $sn;

    public $course;
    public $street;
    public $postalCode;
    public $city;
    public $phone;
    
    public $picture;
    public $pictureUrl;

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
        $this->givenName = $user->getFirstAttribute('givenName');
        $this->sn = $user->getFirstAttribute('sn');
        $this->email = $user->getFirstAttribute('mail');
        $this->course = $user->getFirstAttribute('description');
        $this->street = $user->getFirstAttribute('street');
        $this->postalCode = $user->getFirstAttribute('postalCode');
        $this->city = $user->getFirstAttribute('l');
        $this->phone = $user->getFirstAttribute('telephoneNumber');
        $this->pictureUrl = $user->getFirstAttribute('jpegPhoto');
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
        $user->setAttribute('givenName', $this->givenName);
        $user->setAttribute('sn', $this->sn);
        $user->setAttribute('cn', $this->givenName . ' ' . $this->sn);
        $user->setAttribute('description', $this->course);
        $user->setAttribute('street', $this->street);
        $user->setAttribute('postalCode', $this->postalCode);
        $user->setAttribute('l', $this->city);
        $user->setAttribute('telephoneNumber', $this->phone);
        $user->save();
        return redirect()->route('profile')->with('message', __('Saved'));
    }
}
