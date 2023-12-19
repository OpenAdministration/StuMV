<?php

namespace Tests\Feature\Auth;

use App\Models\Domain;
use App\Models\Realm;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_registration_screen_can_be_rendered_and_livewire_is_there(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSeeLivewire('register-user');
    }

    public function test_new_users_can_register(): void
    {
        $realm = Realm::factory(1)->has(Domain::factory())->create()->first();
        $dom = $realm->domains()->first();

        $response = Livewire::test('register-user')
            ->set('user.email', 'john.doe@' . $dom->name)
            ->set('user.username', 'j.doe')
            ->set('password', '123$abcD')
            ->set('password_confirmation', '123$abcD')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
        $this->assertEquals('John Doe', auth()->user()->full_name);
        $response->assertRedirect(RouteServiceProvider::home());
    }

    public function test_domain_is_not_for_registration():void {
        $realm = Realm::factory(1)->has(Domain::factory()->noRegistration())->create()->first();
        $dom = $realm->domains()->first();

        Livewire::test('register-user')
            ->set('user.email', 'john.doe@' . $dom->name)->send()
            ->assertHasErrors(['domain']);
    }

    public function test_domain_does_not_exist_registration_not_possible() : void {
        Livewire::test('register-user')
            ->set('user.email', $this->faker->companyEmail())->send()
            ->assertHasErrors(['domain']);
    }

    public function test_unfinished_email_for_registration() : void
    {
        Livewire::test('register-user')
            ->set('user.email', 'jon.')->send()
            ->assertHasErrors(['user.email']);
    }

    public function test_passwords_for_registration() : void
    {
        $short = $this->faker->password(1,7); // to short
        $small = 'abcdefgh'; // no uppercase
        $cased = 'Abcdefgh'; // no number
        $number = 'Abcdefg1'; // no symbol
        $symbol = 'Abcdef1$'; // ok

        Livewire::test('register-user')
            ->set('password', $short)->send()
            ->assertHasErrors(['password'])
            ->set('password', $small)->send()
            ->assertHasErrors(['password'])
            ->set('password', $cased)->send()
            ->assertHasErrors(['password'])
            ->set('password', $number)->send()
            ->assertHasErrors(['password'])
            ->set('password', $symbol)->send()
            ->assertHasErrors(['password'])
            ->set('password_confirmation', $symbol)->send()
            ->assertHasNoErrors(['password']);
    }
}
