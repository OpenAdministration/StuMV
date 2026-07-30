<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * Every other real-login helper in this app's tests (TestLdap::member() and
 * friends) generates a random, never-retrieved password, since almost every
 * test just needs an authenticated App\Models\User and uses actingAs() -
 * this one exists specifically so a test can drive the *real* username/password
 * login form (App\Http\Controllers\Auth\AuthenticatedSessionController::store()),
 * which is what actually stamps session('auth_time').
 */
function makeLdapMemberWithKnownPassword(Community $community, string $password): User
{
    $uid = 'testusr'.bin2hex(random_bytes(4));
    LdapUser::findByUsername($uid)?->delete();

    $ldap = new LdapUser([
        'uid' => $uid,
        'cn' => 'Test '.$uid,
        'sn' => 'User',
        'givenName' => 'Test',
        'mail' => $uid.'@example.test',
        'userPassword' => '{ARGON2}'.password_hash($password, PASSWORD_ARGON2ID),
    ]);
    $ldap->setDn("uid=$uid,".$community->peopleDn());
    $ldap->save();
    $ldap->refresh();

    return TestLdap::databaseUser($ldap, $community);
}

test('logging in stamps session auth_time', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $user = makeLdapMemberWithKnownPassword($community, $password);

    $before = time();

    $this->post(route('realm.login', ['realm' => $uid]), [
        'uid' => $user->username,
        'password' => $password,
    ])->assertRedirect();

    expect(session('auth_time'))->toBeInt()
        ->and(session('auth_time'))->toBeGreaterThanOrEqual($before)
        ->and(session('auth_time'))->toBeLessThanOrEqual(time());
});

test('the id_token carries an auth_time claim matching the login timestamp', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $user = makeLdapMemberWithKnownPassword($community, $password);

    $before = time();
    $this->post(route('realm.login', ['realm' => $uid]), [
        'uid' => $user->username,
        'password' => $password,
    ])->assertRedirect();

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Auth Time Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
    ]));

    parse_str(parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->post(route('realm.passport.token', ['realm' => $uid]), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://example.test/callback',
        'code' => $query['code'],
    ]);

    [, $payload] = explode('.', (string) $token->json('id_token'));
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect($claims['auth_time'])->toBeInt()
        ->and($claims['auth_time'])->toBeGreaterThanOrEqual($before)
        ->and($claims['auth_time'])->toBeLessThanOrEqual(time());
});

test('an authorize request with a satisfied max_age proceeds without forcing re-login', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $user = makeLdapMemberWithKnownPassword($community, $password);

    $this->post(route('realm.login', ['realm' => $uid]), [
        'uid' => $user->username,
        'password' => $password,
    ])->assertRedirect();

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Fresh MaxAge Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorize = $this->get(route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'max_age' => 3600,
    ]));

    $authorize->assertRedirect();
    expect($authorize->headers->get('Location'))->toStartWith('https://example.test/callback?code=');
    $this->assertAuthenticated();
});

test('an authorize request with an expired max_age forces re-authentication, then resumes the original request', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $user = makeLdapMemberWithKnownPassword($community, $password);

    $this->post(route('realm.login', ['realm' => $uid]), [
        'uid' => $user->username,
        'password' => $password,
    ])->assertRedirect();

    // Simulate a login that happened long ago, without waiting for real time
    // to pass.
    session(['auth_time' => time() - 7200]);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Stale MaxAge Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $uid, 'requires_consent' => false])->save();

    $authorizeUrl = route('realm.passport.authorizations.authorize', [
        'realm' => $uid,
        'client_id' => $client->id,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'max_age' => 60,
    ]);

    $this->get($authorizeUrl)
        ->assertRedirect(route('realm.login', ['realm' => $uid]));

    $this->assertGuest();

    // Re-authenticating should bounce straight back to the very same
    // /authorize request (redirect()->intended(), stored by the framework's
    // own redirect()->guest() when EnforceMaxAge threw), not just the
    // dashboard. Compared by path + query params rather than the exact
    // string - the stored "intended" URL and route()'s own rebuild of it can
    // legitimately order query params differently.
    $resumed = $this->post(route('realm.login', ['realm' => $uid]), [
        'uid' => $user->username,
        'password' => $password,
    ]);

    $resumed->assertRedirect();
    $expected = parse_url($authorizeUrl);
    $actual = parse_url($resumed->headers->get('Location'));
    parse_str($expected['query'], $expectedQuery);
    parse_str($actual['query'], $actualQuery);
    ksort($expectedQuery);
    ksort($actualQuery);

    expect($actual['path'])->toBe($expected['path'])
        ->and($actualQuery)->toBe($expectedQuery);
});
