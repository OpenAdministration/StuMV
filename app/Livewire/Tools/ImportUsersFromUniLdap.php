<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use App\Ldap\Uni\User as UniLdapUser;
use App\Ldap\User;
use App\Rules\UniqueEmail;
use Flux\Flux;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ImportUsersFromUniLdap extends Component
{
    #[Locked]
    public string $uid;

    public bool $searchCompleted = false;

    public bool $userNotFound = false;

    public string $email = '';

    #[Validate('required|string|min:3|max:255|regex:/^[0-9a-zA-Z_\-\.]*$/')]
    public string $username = '';

    #[Validate('required|string|max:255')]
    public string $firstname = '';

    #[Validate('required|string|max:255')]
    public string $lastname = '';

    protected function rules()
    {
        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail,
            ],
        ];
    }

    public function mount(Community $realm)
    {
        abort_unless(filled(config('ldap.connections.uni.base_dn')), 404);

        $this->uid = $realm->getFirstAttribute('ou');
    }

    public function render()
    {
        return view('livewire.tools.import-users-from-uni-ldap');
    }

    public function getUserData()
    {
        $this->searchCompleted = false;

        try {
            $matches = UniLdapUser::where('mail', '=', $this->email)->get();
        } catch (LdapRecordException) {
            $matches = collect();
        }

        if ($matches->count() === 1) {
            $entry = $matches->first();
            $this->firstname = $entry->getFirstAttribute('givenname');
            $this->lastname = $entry->getFirstAttribute('sn');

            $split = explode('@', $this->email);
            $this->username = str_replace(['-', '_', '.'], '', $split[0] ?? '');
        } else {
            $this->userNotFound = true;
        }

        $this->searchCompleted = true;
    }

    public function createUser()
    {
        $this->validate();

        $community = Community::findByOrFail('ou', $this->uid);

        // Add user to LDAP
        $user = new User([
            'uid' => $this->username,
            'cn' => trim($this->firstname.' '.$this->lastname),
            'sn' => $this->lastname,
            'givenName' => $this->firstname,
            'mail' => $this->email,
            'userPassword' => '{ARGON2}'.password_hash(Str::uuid(), PASSWORD_ARGON2ID),
            // usually ldap SHOULD hash it itself - did not work
        ]);
        $user->setDn("uid=$this->username,ou=People,{base}");
        try {
            $user->save();
            $community->membersGroup()->members()->attach($user);

            \App\Models\User::create([
                'username' => $this->username,
                'full_name' => trim($this->firstname.' '.$this->lastname),
                'email' => $this->email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::uuid()),
                'realm' => $this->uid,
            ]);

            Flux::toast(variant: 'success', text: __('tools.userCreatedSuccessfully'));

            $this->searchCompleted = false;
            $this->email = '';
            $this->username = '';
            $this->firstname = '';
            $this->lastname = '';
        } catch (LdapRecordException $ldapRecordException) {
            dump($ldapRecordException->getDetailedError());
        }
    }
}
