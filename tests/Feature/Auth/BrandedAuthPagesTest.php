<?php

use App\Models\RealmBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a realm\'s branding logo is shown on the logout confirmation page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create(['realm' => $uid, 'logo_id' => 'test-logo.webp']);
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $response = $this->get(route('realm.logout.confirm', ['realm' => $uid]));

    $response->assertOk()
        ->assertSeeHtml(asset('storage/realm-branding/test-logo.webp'));
});

test('a realm\'s branding logo is shown on the verify-email page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create(['realm' => $uid, 'logo_id' => 'test-logo.webp']);
    $user = TestLdap::member($community);
    $user->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($user);

    $response = $this->get(route('verification.notice', ['realm' => $uid]));

    $response->assertOk()
        ->assertSeeHtml(asset('storage/realm-branding/test-logo.webp'));
});

test('a realm\'s branding logo is shown on the oauth authorization page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create(['realm' => $uid, 'logo_id' => 'test-logo.webp']);
    $user = TestLdap::member($community);
    $this->actingAs($user);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Branding Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();

    $response = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    $response->assertOk()
        ->assertSeeHtml(asset('storage/realm-branding/test-logo.webp'));
});

test('an enabled login notice is shown on the login page, sanitized', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create([
        'realm' => $uid,
        'login_notice_enabled' => true,
        'login_notice' => '<p><strong>Wartung</strong> am Montag</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
    ]);

    $this->get(route('realm.login', ['realm' => $uid]))
        ->assertOk()
        ->assertSeeHtml('<strong>Wartung</strong> am Montag')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('javascript:alert');
});

test('a disabled login notice is not shown on the login page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    RealmBranding::create(['realm' => $uid, 'login_notice_enabled' => false, 'login_notice' => '<p>Wartung am Montag</p>']);

    $this->get(route('realm.login', ['realm' => $uid]))
        ->assertOk()
        ->assertDontSee('Wartung am Montag');
});
