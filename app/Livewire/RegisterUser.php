<?php

namespace App\Livewire;

use App\Ldap\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Component;

class RegisterUser extends Component
{
    //public User $user;

    public string $email = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $username = '';

    private string $domain = '';

    /** extra, so password can stay hidden in $user */
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules() : array
    {
        return [
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];
        /*[
            'user.full_name' => ['required', 'string', 'max:255'],
            'user.username' => ['required', 'string', 'max:255', 'unique:App\Models\User,username', 'regex:/[a-z_\.]/i' ],
            'user.email' => [
                'required', 'string', 'email', 'max:255', 'unique:App\Models\User,email',
            ],
            'domain' => [Rule::exists(Domain::class, 'name')->where('for_registration', true)],
            'password' => [
                'required',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];*/
    }

    /**
     * Do some stuff if email after email was changed
     * @return void
     */
    public function updatedUserEmail(): void
    {
        $split = explode('@', $this->email);
        $this->domain = $split[1] ?? 'false';
        $this->validateOnly('email');
        //$this->validateOnly('domain');
        // if mail is valid try to prefill the fullName of the user
        $this->name = ucwords(str_replace(['-', '_', '.'], ' ', $split[0]));
        $this->validateOnly('name');

    }

    public function updated($propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    public function render()
    {
        return view('livewire.register-user')->layout('layouts.guest');
    }

    public function mount() : void
    {
    }

    public function store(){

        $this->validate();

        $user = new User([
            'uid' => $this->username,
            'cn' => $this->first_name  . ' ' . $this->last_name,
            'sn'  => $this->last_name,
            'mail' => $this->email,
            'userPassword'  => "{ARGON2}" . Hash::make($this->password),
            // usually ldap SHOULD hash it itself - did not work
        ]);
        $user->setDn("uid=$this->username,ou=People,{base}");
        try {
            $user->save();
        }  catch (LdapRecordException $ldapRecordException){
            dump($ldapRecordException->getDetailedError());
        }

        event(new Registered($user));

        Auth::attempt([$this->username, $this->password]);

        return redirect()->route('login')->with('message', __('Successfully Registered'));
    }
}
