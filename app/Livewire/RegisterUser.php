<?php

namespace App\Livewire;

use App\Ldap\Domain;
use App\Ldap\User;
use App\Providers\RouteServiceProvider;
use App\Rules\DomainRegistrationRule;
use App\Rules\UniqueDomain;
use App\Rules\UniqueEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class RegisterUser extends Component
{
    //public User $user;

    public string $email = '';
    #[Validate('required|string|alpha|max:255')]
    public string $first_name = '';
    #[Validate('required|string|alpha|max:255')]
    public string $last_name = '';

    #[Validate('required|string|min:3|max:255|alpha_dash')]
    public string $username = '';

    #[Validate]
    public string $password = '';
    #[Validate]
    public string $password_confirmation = '';

    public string $domain = '';

    protected function rules() : array
    {
        return [
            'email' => [
                'required',
                'email',
                new UniqueEmail(),
            ],
            'password' => [
                'required',
                Password::default(),
                'confirmed',
            ],
            'domain' => [
                'required',
                //new \dacoto\DomainValidator\Validator\Domain(),
                new DomainRegistrationRule()
            ],
        ];
    }

    /**
     * Do some stuff if email was changed
     * @return void
     */
    public function updatedEmail(): void
    {
        $this->validateOnly('email');
        $split = explode('@', $this->email);
        $this->domain = $split[1] ?? 'false';
        $this->validateOnly('domain');
        // if mail is valid try to prefill the fullName of the user
        $this->username = str_replace('.', '-', $split[0]);
        $guessedName = explode(" ", ucwords(str_replace(['-', '_', '.'], ' ', $split[0])),2);
        $this->first_name = $guessedName[0] ?? $this->first_name ?? "";
        $this->last_name = $guessedName[1] ?? $this->last_name ?? "";
        $this->validateOnly('username');
    }

    public function render()
    {
        return view('livewire.register-user')->layout('layouts.guest');
    }

    public function mount() : void
    {
    }

    public function save(){

        $this->updatedEmail();
        $this->validate();
        $domain = Domain::findByOrFail('dc', $this->domain);
        $community = $domain->community();
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
            $community->membersGroup()->members()->attach($user);
        }  catch (LdapRecordException $ldapRecordException){
            dump($ldapRecordException->getDetailedError());
        }

        event(new Registered($user));

        Auth::attempt([$this->username, $this->password]);

        return redirect()->route('verification.notice')->with('message', __('Successfully Registered'));
    }
}
