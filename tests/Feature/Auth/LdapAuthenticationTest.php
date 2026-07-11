<?php

namespace Tests\Feature\Auth;

use App\Ldap\User as LdapUser;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end coverage of the LDAP-backed registration + login flow against the
 * dockerised OpenLDAP (see docker/openldap/). Requires the LDAP container to be
 * reachable on the connection configured in .env.testing; CI starts it as a
 * service. The @example.test domain and the "testcom" community it belongs to
 * are part of the container's seed data.
 */
class LdapAuthenticationTest extends TestCase
{
    private const string DOMAIN = 'example.test';

    private string $username;

    /** Random per run: satisfies Password::defaults() and is not a leaked password. */
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        // Unique per run so repeated local runs don't collide; removed in tearDown.
        $this->username = 'phptest'.bin2hex(random_bytes(4));
        $this->password = 'Aa1!'.bin2hex(random_bytes(8));
        $this->deleteLdapUser();
    }

    protected function tearDown(): void
    {
        $this->deleteLdapUser();
        parent::tearDown();
    }

    private function deleteLdapUser(): void
    {
        LdapUser::findByUsername($this->username)?->delete();
    }

    private function register(): void
    {
        Livewire::test('register-user')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('username', $this->username)
            ->set('email', $this->username.'@'.self::DOMAIN)
            ->set('password', $this->password)
            ->set('password_confirmation', $this->password)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_a_user_can_register_into_ldap(): void
    {
        $this->register();

        $ldapUser = LdapUser::findByUsername($this->username);
        $this->assertNotNull($ldapUser, 'registered user should exist in LDAP');
        $this->assertSame(
            $this->username.'@'.self::DOMAIN,
            $ldapUser->getFirstAttribute('mail')
        );
    }

    public function test_a_registered_user_can_log_in(): void
    {
        $this->register();
        auth()->logout();
        $this->assertGuest();

        $this->post('/login', ['uid' => $this->username, 'password' => $this->password])
            ->assertSessionHasNoErrors()
            ->assertRedirect(RouteServiceProvider::home());

        $this->assertAuthenticated();
        $this->assertInstanceOf(User::class, auth()->user());
    }

    public function test_login_is_rejected_with_a_wrong_password(): void
    {
        $this->register();
        auth()->logout();

        $this->post('/login', ['uid' => $this->username, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('uid');

        $this->assertGuest();
    }
}
