<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use Flux\Flux;
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

    public $currentUsername;

    public $userIsActive = true;

    public function mount($username)
    {
        $this->authorize('manageProfile', [User::class, $username]);
        $this->currentUsername = $username;
        $user = $this->findUserWithLockStatus($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');
        $this->givenName = $user->getFirstAttribute('givenName');
        $this->sn = $user->getFirstAttribute('sn');
        $this->email = $user->getFirstAttribute('mail');
        $this->course = $user->getFirstAttribute('description');
        $this->street = $user->getFirstAttribute('street');
        $this->postalCode = $user->getFirstAttribute('postalCode');
        $this->city = $user->getFirstAttribute('l');
        $this->phone = $user->getFirstAttribute('telephoneNumber');

        if ($user->hasAttribute('pwdAccountLockedTime') && $user->getFirstAttribute('pwdAccountLockedTime') === '00000101000000Z') {
            $this->userIsActive = false;
        } else {
            $this->userIsActive = true;
        }
    }

    public function render()
    {
        return view('livewire.profile.profile')->title(__('profile.title', ['name' => $this->givenName.' '.$this->sn]));
    }

    public function save()
    {
        $this->validate();
        $user = $this->findUserWithLockStatus($this->uid);
        $user->setAttribute('mail', $this->email);
        $user->setAttribute('givenName', $this->givenName);
        $user->setAttribute('sn', $this->sn);
        $user->setAttribute('cn', $this->givenName.' '.$this->sn);
        $user->setAttribute('description', $this->course);
        $user->setAttribute('street', $this->street);
        $user->setAttribute('postalCode', $this->postalCode);
        $user->setAttribute('l', $this->city);
        $user->setAttribute('telephoneNumber', $this->phone);

        $isCurrentlyLocked = $user->hasAttribute('pwdAccountLockedTime');

        if ($this->userIsActive === $isCurrentlyLocked) {
            abort_unless(auth()->user()->can('superadmin', \App\Models\User::class), 403);
        }

        if ($this->userIsActive && $isCurrentlyLocked) {
            $user->removeAttribute('pwdAccountLockedTime');
        } elseif (! $this->userIsActive) {
            $user->setAttribute('pwdAccountLockedTime', '00000101000000Z');
        }

        $user->save();

        \App\Models\User::where('username', $this->uid)->update([
            'full_name' => $this->givenName.' '.$this->sn,
        ]);

        Flux::toast(variant: 'success', text: __('common.saved'));
        $this->redirect('/profile/'.$this->uid, navigate: true);
    }

    /**
     * pwdAccountLockedTime is an operational attribute: the LDAP server only
     * returns it when explicitly named in the select, never via a plain "*"
     * fetch. Without this, the account-active status can never be read back.
     */
    protected function findUserWithLockStatus(string $username): User
    {
        return User::query()
            ->select(['*', 'pwdAccountLockedTime'])
            ->where('uid', '=', $username)
            ->first() ?? abort(404);
    }
}
