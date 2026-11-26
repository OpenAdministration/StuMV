<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Profile\Account;
use App\Models\ActivityLog;
use App\Models\User as DbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a super admin sees the account-active switch and can disable a user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->assertSee(__('profile.user_is_active'))
        ->set('userIsActive', false)
        ->assertHasNoErrors();

    // pwdAccountLockedTime is an operational attribute, so it must be
    // explicitly named in the select or the server won't return it.
    $ldap = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($ldap->getFirstAttribute('pwdAccountLockedTime'))->toBe('00000101000000Z');
});

test('a realm admin sees the account-active switch and can disable a user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->assertSee(__('profile.user_is_active'))
        ->set('userIsActive', false)
        ->assertHasNoErrors();

    $ldap = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($ldap->getFirstAttribute('pwdAccountLockedTime'))->toBe('00000101000000Z')
        ->and(ActivityLog::sole()->only(['action', 'subject_id']))->toBe(['action' => 'member.locked', 'subject_id' => $target->uid]);
});

test('a realm admin can re-enable a previously disabled user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    $ldap = LdapUser::findByUsername($target->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->set('userIsActive', true)
        ->assertHasNoErrors();

    $fresh = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($fresh->hasAttribute('pwdAccountLockedTime'))->toBeFalse()
        ->and(ActivityLog::sole()->action)->toBe('member.unlocked');
});

test('a disabled user shows as inactive when the account tab loads', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    $ldap = LdapUser::findByUsername($target->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->assertSet('userIsActive', false);
});

test('a super admin can re-enable a previously disabled user', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsSuperAdmin();

    $ldap = LdapUser::findByUsername($target->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->assertSet('userIsActive', false)
        ->set('userIsActive', true)
        ->assertHasNoErrors();

    $fresh = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $target->username)
        ->first();
    expect($fresh->hasAttribute('pwdAccountLockedTime'))->toBeFalse();
});

test('a regular user does not see the account-active switch on their own account tab', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $user->username])
        ->assertDontSee(__('profile.user_is_active'));
});

test('a non-superadmin cannot lock their own account by tampering with the field directly', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $user->username])
        ->set('userIsActive', false)
        ->assertForbidden();

    $ldap = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $user->username)
        ->first();
    expect($ldap->hasAttribute('pwdAccountLockedTime'))->toBeFalse();
});

test('a non-superadmin cannot re-enable a locked account by tampering with the field directly', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    $ldap = LdapUser::findByUsername($user->username);
    $ldap->setAttribute('pwdAccountLockedTime', '00000101000000Z');
    $ldap->save();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $user->username])
        ->set('userIsActive', true)
        ->assertForbidden();

    $fresh = LdapUser::query()
        ->select(['*', 'pwdAccountLockedTime'])
        ->where('uid', '=', $user->username)
        ->first();
    expect($fresh->hasAttribute('pwdAccountLockedTime'))->toBeTrue();
});

test('a user can delete their own account and is logged out', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $user->username])
        ->set('deleteConfirmation', $user->username)
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    expect(LdapUser::query()->in($community->peopleDn())->where('uid', '=', $user->username)->first())->toBeNull()
        ->and(DbUser::whereKey($user->id)->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'member.deleted')->exists())->toBeTrue();
    $this->assertGuest();
});

test('deleting an account requires the username as confirmation', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $user->username])
        ->set('deleteConfirmation', 'wrong')
        ->call('deleteAccount')
        ->assertHasErrors('deleteConfirmation');

    expect(LdapUser::query()->in($community->peopleDn())->where('uid', '=', $user->username)->first())->not->toBeNull();
    $this->assertAuthenticated();
});

test('a realm admin cannot delete another account', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->assertDontSee(__('profile.delete_account'))
        ->set('deleteConfirmation', $target->username)
        ->call('deleteAccount')
        ->assertForbidden();

    expect(LdapUser::query()->in($community->peopleDn())->where('uid', '=', $target->username)->first())->not->toBeNull();
});

test('a super admin can delete another account and stays logged in', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    $admin = actingAsSuperAdmin();

    Livewire::test(Account::class, ['realm' => $community, 'username' => $target->username])
        ->set('deleteConfirmation', $target->username)
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.members', ['realm' => $community->getShortCode()]));

    expect(LdapUser::query()->in($community->peopleDn())->where('uid', '=', $target->username)->first())->toBeNull();
    $this->assertAuthenticatedAs($admin);
});
