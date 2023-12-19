<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class LdapAuthenticationTest extends TestCase
{
    use WithFaker;

    public function test_registration_works(): array
    {
        //$fakeLdap = DirectoryEmulator::setup('default');
        $fakeLdap = null;

        $username = $this->faker->userName;
        $password = "Password#12345";

        Livewire::test('register-user')
            ->set('username', $username)
            ->set('first_name', $this->faker->firstName)
            ->set('last_name', $this->faker->lastName . " Test")
            ->set('email', $this->faker->email)
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('store')
            ->assertHasNoErrors();

        $user = \App\Ldap\User::where('uid', '=' , $username);
        $this->assertTrue($user->exists());

        return [$fakeLdap, $user->first(), $password];
    }

    /**
     * @depends test_registration_works
     */
    public function test_auth_works($args)
    {
        [$fakeLdap, $user, $password] = $args;

        $this->post('/login', [
            'uid' => $user->uid[0],
            'password' => $password,
        ])->assertSessionHasNoErrors()
          ->assertRedirect(RouteServiceProvider::home());

        $user = Auth::user();

        $this->assertInstanceOf(\App\Models\User::class, $user);
    }
}
