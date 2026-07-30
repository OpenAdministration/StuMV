<?php

use App\Livewire\Profile\Sessions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * The test HTTP client doesn't run terminable middleware (session
 * persistence for the database driver happens in StartSession::terminate(),
 * which Laravel's TestCase never invokes) - so rows are seeded directly
 * here instead of relying on a real request to populate the sessions table,
 * the same shape App\Livewire\Profile\Sessions itself queries.
 */
function seedSession(User $user, string $id, array $overrides = []): void
{
    DB::table('sessions')->insert(array_merge([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0 (Test Browser)',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ], $overrides));
}

test('the last login time is shown once LDAP has recorded a successful bind', function (): void {
    $community = newCommunity();
    $ldapUser = TestLdap::makeUser(null, $community);
    $knownPassword = 'Aa1!'.bin2hex(random_bytes(6));
    $ldapUser->setAttribute('userPassword', '{ARGON2}'.password_hash($knownPassword, PASSWORD_ARGON2ID));
    $ldapUser->save();
    $user = TestLdap::databaseUser($ldapUser, $community);

    // A real bind (not actingAs(), which never touches LDAP) - only this
    // makes slapd's core last-bind tracking (olcLastBind) record
    // pwdLastSuccess on the entry.
    $this->post('/'.$community->getShortCode().'/login', ['uid' => $user->username, 'password' => $knownPassword])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticated();

    $lastLogin = Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->viewData('lastLogin');

    expect($lastLogin)->not->toBeNull()
        ->and($lastLogin->diffInSeconds(now()))->toBeLessThan(30);
});

test('no last login time is shown for an account that was never bound as itself', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    $component = Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username]);

    expect($component->viewData('lastLogin'))->toBeNull();
    $component->assertDontSee(__('profile.sessions_last_login', ['datetime' => '']));
});

test('a user sees their own sessions, with the current one marked', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $currentId = session()->getId();
    seedSession($user, $currentId);
    seedSession($user, 'other-session-id-'.bin2hex(random_bytes(8)), ['ip_address' => '198.51.100.5']);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->assertSee('203.0.113.10')
        ->assertSee('198.51.100.5')
        ->assertSee(__('profile.sessions_current_device'));
});

test('a user can log out one of their other sessions', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    seedSession($user, session()->getId());
    $otherId = 'other-session-id-'.bin2hex(random_bytes(8));
    seedSession($user, $otherId);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('logoutSession', $otherId)
        ->assertHasNoErrors();

    expect(DB::table('sessions')->where('id', $otherId)->exists())->toBeFalse();
});

test('a user cannot log out their own current session through this action', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $currentId = session()->getId();
    seedSession($user, $currentId);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('logoutSession', $currentId)
        ->assertForbidden();

    expect(DB::table('sessions')->where('id', $currentId)->exists())->toBeTrue();
});

test('a user cannot log out a session belonging to a different account', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $otherUser = TestLdap::member($community);
    $foreignSessionId = 'foreign-session-'.bin2hex(random_bytes(8));
    seedSession($user, session()->getId());
    seedSession($otherUser, $foreignSessionId);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('logoutSession', $foreignSessionId)
        ->assertStatus(404);

    expect(DB::table('sessions')->where('id', $foreignSessionId)->exists())->toBeTrue();
});

test('a user can log out all their other sessions at once, keeping the current one', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $currentId = session()->getId();
    seedSession($user, $currentId);
    $otherIdA = 'other-a-'.bin2hex(random_bytes(8));
    $otherIdB = 'other-b-'.bin2hex(random_bytes(8));
    seedSession($user, $otherIdA);
    seedSession($user, $otherIdB);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('logoutOtherSessions')
        ->assertHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([$currentId]);
});

test('logging out all other sessions never touches a different account\'s sessions', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $otherUser = TestLdap::member($community);
    $foreignSessionId = 'foreign-session-'.bin2hex(random_bytes(8));
    seedSession($user, session()->getId());
    seedSession($otherUser, $foreignSessionId);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('logoutOtherSessions');

    expect(DB::table('sessions')->where('id', $foreignSessionId)->exists())->toBeTrue();
});

test('the sessions table can be sorted by IP address', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    seedSession($user, session()->getId(), ['ip_address' => '203.0.113.10']);
    seedSession($user, 'other-'.bin2hex(random_bytes(8)), ['ip_address' => '198.51.100.5']);

    $component = Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('sortBy', 'ip_address')
        ->assertSet('sortField', 'ip_address')
        ->assertSet('sortDirection', 'asc');

    $ips = $component->viewData('sessions')->pluck('ip_address')->all();
    expect($ips)->toBe(['198.51.100.5', '203.0.113.10']);
});

test('sortBy toggles direction when clicking the same column again', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    seedSession($user, session()->getId());

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $user->username])
        ->call('sortBy', 'last_activity')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'last_activity')
        ->assertSet('sortDirection', 'desc');
});

test('a user cannot manage someone else\'s sessions', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('a super admin can view and manage another user\'s sessions', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();
    seedSession($target, 'target-session-'.bin2hex(random_bytes(8)), ['ip_address' => '192.0.2.20']);

    Livewire::test(Sessions::class, ['realm' => $community, 'username' => $target->username])
        ->assertSee('192.0.2.20');
});

test('a realm admin cannot manage sessions in a different realm', function (): void {
    $adminRealm = newCommunity();
    $otherRealm = newCommunity();
    $target = TestLdap::member($otherRealm);
    actingAsAdmin($adminRealm);

    Livewire::test(Sessions::class, ['realm' => $otherRealm, 'username' => $target->username])
        ->assertForbidden();
});
